<?php

namespace App\Services\Analysis;

use App\Contracts\AnalysisStarter;
use App\Contracts\ProviderRegistry;
use App\Data\AnalysisInput;
use App\Data\AnalysisStartResult;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisTier;
use App\Exceptions\AnalysisRejected;
use App\Models\Analysis;
use App\Models\Company;
use App\Services\Detection\TextNormalizer;
use App\Services\Security\TurnstileVerifier;
use App\Support\Domain;

class DefaultAnalysisStarter implements AnalysisStarter
{
    public function __construct(
        private readonly TurnstileVerifier $turnstile,
        private readonly StartLimits $limits,
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

        $this->limits->assertEnabled();
        $this->limits->assertIpAllowed($input->ip);

        $configHash = $this->limits->configHash($domain, $market, $language, $location, (int) settings('query_count'));

        $result = $this->limits->locked($domain, fn () => $this->startLocked($input, $url, $domain, $name, $market, $location, $language, $configHash));

        return $this->limits->dispatch($result);
    }

    private function startLocked(AnalysisInput $input, string $url, string $domain, string $name, string $market, ?string $location, string $language, string $configHash): AnalysisStartResult
    {
        if (($reusable = $this->limits->reusable($configHash)) !== null) {
            return new AnalysisStartResult($reusable, true);
        }

        // Audits are paid for by the lead's email, not by the free quota.
        $window = Analysis::query()
            ->where('domain', $domain)
            ->where('tier', AnalysisTier::Check->value)
            ->where('status', '!=', AnalysisStatus::Failed->value)
            ->where('created_at', '>=', now()->subHours((int) settings('domain_window_hours')));
        if ((clone $window)->count() >= (int) settings('free_analyses_per_domain')) {
            throw new AnalysisRejected(AnalysisRejected::DOMAIN_LIMIT, (clone $window)->latest()->value('uuid'));
        }

        $this->limits->assertDailyAndBudget();

        $company = Company::query()->firstOrNew(['domain' => $domain]);
        $company->fill(['name' => $name, 'normalized_name' => TextNormalizer::brandName($name)]);
        $company->save();

        $analysis = Analysis::create([
            'company_id' => $company->id,
            'tier' => AnalysisTier::Check,
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
            'ip_hash' => $this->limits->ipHash($input->ip),
        ]);

        $this->limits->hitIp($input->ip);

        return new AnalysisStartResult($analysis->refresh(), false);
    }
}
