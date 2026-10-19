<?php

namespace App\Services\Analysis;

use App\Data\AnalysisStartResult;
use App\Enums\AnalysisStatus;
use App\Exceptions\AnalysisRejected;
use App\Jobs\RunAnalysis;
use App\Models\AiProvider;
use App\Models\Analysis;
use App\Models\PromptTemplate;
use App\Services\Detection\TextNormalizer;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/** Limits and reuse rules shared by the free-check starter and the audit starter. */
class StartLimits
{
    /** In-flight analyses older than this are considered stuck and no longer block a new one. */
    public const STALE_AFTER_MINUTES = 30;

    public function __construct(private readonly SpendingGuard $spending) {}

    /** @throws AnalysisRejected */
    public function assertEnabled(): void
    {
        if (! settings('analyses_enabled') || ! AiProvider::query()->enabled()->exists()) {
            throw new AnalysisRejected(AnalysisRejected::DISABLED);
        }
    }

    public function ipHash(string $ip): string
    {
        return hash('sha256', $ip.config('app.key'));
    }

    public function ipKey(string $ip): string
    {
        return 'analysis-start:ip:'.$this->ipHash($ip);
    }

    /** @throws AnalysisRejected */
    public function assertIpAllowed(string $ip): void
    {
        if (RateLimiter::tooManyAttempts($this->ipKey($ip), (int) settings('ip_analyses_per_hour'))) {
            throw new AnalysisRejected(AnalysisRejected::RATE_LIMITED);
        }
    }

    public function hitIp(string $ip): void
    {
        RateLimiter::hit($this->ipKey($ip), 3600);
    }

    /** A completed analysis with the same config within cache_hours, else an in-flight duplicate. */
    public function reusable(string $configHash): ?Analysis
    {
        return Analysis::query()
            ->where('config_hash', $configHash)
            ->where('status', AnalysisStatus::Completed->value)
            ->where('completed_at', '>=', now()->subHours((int) settings('cache_hours')))
            ->latest('completed_at')
            ->first()
            ?? Analysis::query()
                ->where('config_hash', $configHash)
                ->whereNotIn('status', [AnalysisStatus::Completed->value, AnalysisStatus::Failed->value])
                ->where('created_at', '>=', now()->subMinutes(self::STALE_AFTER_MINUTES))
                ->latest()
                ->first();
    }

    /** @throws AnalysisRejected */
    public function assertDailyAndBudget(): void
    {
        if (Analysis::query()->where('created_at', '>=', now()->startOfDay())->count() >= (int) settings('max_analyses_per_day')) {
            throw new AnalysisRejected(AnalysisRejected::DAILY_LIMIT);
        }

        if ($this->spending->monthlyBudgetExceeded()) {
            throw new AnalysisRejected(AnalysisRejected::BUDGET_EXCEEDED);
        }
    }

    /**
     * One lock per domain serializes the check-then-create window for every config of that domain.
     *
     * @param  Closure(): AnalysisStartResult  $callback
     *
     * @throws AnalysisRejected
     */
    public function locked(string $domain, Closure $callback): AnalysisStartResult
    {
        try {
            return Cache::lock('analysis-start:'.sha1($domain), 15)->block(10, $callback);
        } catch (LockTimeoutException) {
            throw new AnalysisRejected(AnalysisRejected::RATE_LIMITED);
        }
    }

    public function dispatch(AnalysisStartResult $result): AnalysisStartResult
    {
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

    /** @param  list<string>  $extra  appended parts, e.g. "tier:audit" */
    public function configHash(string $domain, string $market, string $language, ?string $location, int $queryCount, array $extra = []): string
    {
        // A local analysis must never reuse a country-wide one (or another city's).
        $templateIds = PromptTemplate::enabled()->pluck('id')->sort()->values()->implode(',');

        $parts = [$domain, $market, $language, $queryCount, $templateIds, config('mentionai.ai_mode')];
        if ($location !== null) {
            $parts[] = 'location:'.TextNormalizer::fold($location);
        }

        return hash('sha256', implode('|', [...$parts, ...$extra]));
    }
}
