<?php

namespace App\Services\Analysis;

use App\Contracts\AnalysisStarter;
use App\Contracts\ProviderRegistry;
use App\Data\AnalysisInput;
use App\Data\AnalysisStartResult;
use App\Enums\AnalysisStatus;
use App\Exceptions\AnalysisRejected;
use App\Jobs\RunAnalysis;
use App\Models\AiProvider;
use App\Models\Analysis;
use App\Models\Company;
use App\Models\PromptTemplate;
use App\Services\Detection\TextNormalizer;
use App\Services\Security\TurnstileVerifier;
use App\Support\Domain;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class DefaultAnalysisStarter implements AnalysisStarter
{
    /** In-flight analyses older than this are considered stuck and no longer block a new one. */
    private const STALE_AFTER_MINUTES = 30;

    public function __construct(
        private readonly TurnstileVerifier $turnstile,
        private readonly SpendingGuard $spending,
        private readonly ProviderRegistry $registry,
    ) {}

    public function start(AnalysisInput $input): AnalysisStartResult
    {
        $url = Domain::normalizeUrl($input->websiteUrl);
        $domain = Domain::fromUrl($input->websiteUrl);
        $name = trim(preg_replace('/\s+/u', ' ', $input->companyName));
        $market = strtoupper(trim($input->market));
        $language = strtolower(trim($input->language));
        $location = trim(preg_replace('/\s+/u', ' ', (string) $input->location));
        $location = $location !== '' ? mb_substr($location, 0, 120) : null;

        if ($url === null || $domain === null || $name === '' || ! preg_match('/^[A-Z]{2}$/', $market)
            || ! in_array($language, config('mentionai.analysis_languages'), true)) {
            throw new AnalysisRejected(AnalysisRejected::INVALID_URL);
        }

        if (! $this->turnstile->verify($input->turnstileToken, $input->ip)) {
            throw new AnalysisRejected(AnalysisRejected::CAPTCHA_FAILED);
        }

        if (! settings('analyses_enabled') || ! AiProvider::query()->enabled()->exists()) {
            throw new AnalysisRejected(AnalysisRejected::DISABLED);
        }

        $ipKey = 'analysis-start:ip:'.hash('sha256', $input->ip.config('app.key'));
        $perHour = (int) settings('ip_analyses_per_hour');
        if (RateLimiter::tooManyAttempts($ipKey, $perHour)) {
            throw new AnalysisRejected(AnalysisRejected::RATE_LIMITED);
        }

        $configHash = $this->configHash($domain, $market, $language, $location);

        // One lock per domain serializes the check-then-create window for every config of that domain.
        try {
            $result = Cache::lock('analysis-start:'.sha1($domain), 15)->block(10, function () use ($input, $url, $domain, $name, $market, $location, $language, $configHash, $ipKey) {
                return $this->startLocked($input, $url, $domain, $name, $market, $location, $language, $configHash, $ipKey);
            });
        } catch (LockTimeoutException) {
            throw new AnalysisRejected(AnalysisRejected::RATE_LIMITED);
        }

        if (! $result->reused) {
            try {
                RunAnalysis::dispatch($result->analysis->id);
            } catch (Throwable $e) {
                // With the sync queue driver a pipeline error surfaces here; the job's failed() hook already marked the analysis.
                report($e);
                $result->analysis->refresh();
            }
        }

        return $result;
    }

    private function startLocked(AnalysisInput $input, string $url, string $domain, string $name, string $market, ?string $location, string $language, string $configHash, string $ipKey): AnalysisStartResult
    {
        $cacheHours = (int) settings('cache_hours');
        $cached = Analysis::query()
            ->where('config_hash', $configHash)
            ->where('status', AnalysisStatus::Completed->value)
            ->where('completed_at', '>=', now()->subHours($cacheHours))
            ->latest('completed_at')
            ->first();
        if ($cached !== null) {
            return new AnalysisStartResult($cached, true);
        }

        $inFlight = Analysis::query()
            ->where('config_hash', $configHash)
            ->whereNotIn('status', [AnalysisStatus::Completed->value, AnalysisStatus::Failed->value])
            ->where('created_at', '>=', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->latest()
            ->first();
        if ($inFlight !== null) {
            return new AnalysisStartResult($inFlight, true);
        }

        $window = Analysis::query()
            ->where('domain', $domain)
            ->where('status', '!=', AnalysisStatus::Failed->value)
            ->where('created_at', '>=', now()->subHours((int) settings('domain_window_hours')));
        if ((clone $window)->count() >= (int) settings('free_analyses_per_domain')) {
            throw new AnalysisRejected(AnalysisRejected::DOMAIN_LIMIT, (clone $window)->latest()->value('uuid'));
        }

        if (Analysis::query()->where('created_at', '>=', now()->startOfDay())->count() >= (int) settings('max_analyses_per_day')) {
            throw new AnalysisRejected(AnalysisRejected::DAILY_LIMIT);
        }

        if ($this->spending->monthlyBudgetExceeded()) {
            throw new AnalysisRejected(AnalysisRejected::BUDGET_EXCEEDED);
        }

        $company = Company::query()->firstOrNew(['domain' => $domain]);
        $company->fill(['name' => $name, 'normalized_name' => TextNormalizer::brandName($name)]);
        $company->save();

        $analysis = Analysis::create([
            'company_id' => $company->id,
            'company_name' => $name,
            'website_url' => $url,
            'domain' => $domain,
            'market' => $market,
            'location' => $location,
            'language' => $language,
            'industry' => filled($input->industry) ? trim($input->industry) : null,
            'interface_locale' => $input->interfaceLocale,
            'status' => AnalysisStatus::Pending,
            'is_demo' => $this->registry->isMockMode(),
            'config_hash' => $configHash,
            'ip_hash' => hash('sha256', $input->ip.config('app.key')),
        ]);

        RateLimiter::hit($ipKey, 3600);

        return new AnalysisStartResult($analysis->refresh(), false);
    }

    private function configHash(string $domain, string $market, string $language, ?string $location): string
    {
        // A local analysis must never reuse a country-wide one (or another city's).
        $templateIds = PromptTemplate::enabled()->pluck('id')->sort()->values()->implode(',');

        $parts = [$domain, $market, $language, (int) settings('query_count'), $templateIds, config('mentionai.ai_mode')];
        if ($location !== null) {
            $parts[] = 'location:'.TextNormalizer::fold($location);
        }

        return hash('sha256', implode('|', $parts));
    }
}
