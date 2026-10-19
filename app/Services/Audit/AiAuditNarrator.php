<?php

namespace App\Services\Audit;

use App\Contracts\AuditNarrator;
use App\Contracts\ProviderRegistry;
use App\Data\AuditNarrative;
use App\Data\ProviderQuery;
use App\Data\ProviderResult;
use App\Data\SiteCheck;
use App\Models\AiProvider;
use App\Models\Analysis;
use App\Models\ApiUsageLog;
use App\Services\Analysis\SpendingGuard;
use Locale;
use Throwable;

/**
 * One grounded provider call that turns aggregated facts into a verdict, summary and H1/title/meta proposal.
 * The prompt carries aggregates only: never raw AI answers, never the lead's email.
 * Mock mode → deterministic "demo" narrative; any live failure or invalid output → deterministic "rules" narrative.
 */
class AiAuditNarrator implements AuditNarrator
{
    public const LIMITS = ['verdict' => 400, 'summary_item' => 220, 'h1' => 70, 'title' => 65, 'meta_description' => 160];

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly SpendingGuard $spending,
    ) {}

    public function narrate(Analysis $analysis): AuditNarrative
    {
        try {
            $facts = $this->facts($analysis);

            if ($this->registry->isMockMode()) {
                return $this->rules($analysis, $facts, demo: true);
            }

            return $this->live($analysis, $facts) ?? $this->rules($analysis, $facts);
        } catch (Throwable $e) {
            report($e);

            return $this->rules($analysis, null);
        }
    }

    private function live(Analysis $analysis, array $facts): ?AuditNarrative
    {
        $provider = AiProvider::query()->where('slug', (string) settings('audit_narrative_provider'))->first();
        if ($provider === null || ! $provider->is_enabled || $this->spending->monthlyBudgetExceeded()) {
            return null;
        }

        try {
            $result = $this->registry->adapterFor($provider)->query($provider, new ProviderQuery($this->prompt($analysis, $facts), $analysis->language, $analysis->market));
        } catch (Throwable $e) {
            report($e);
            $result = ProviderResult::failure($provider->slug->value, (string) $provider->model, 'provider_error', 'Unexpected adapter error.');
        }

        $narrative = $result->successful ? $this->parse((string) $result->text) : null;
        $this->logUsage($analysis, $provider, $result, $narrative !== null);

        return $narrative;
    }

    /** Builds the prompt; public so tests can assert what leaves the system. */
    public function prompt(Analysis $analysis, array $facts): string
    {
        $language = Locale::getDisplayLanguage($analysis->language, 'en') ?: $analysis->language;

        return implode("\n", [
            'You write the executive summary of a GEO audit: how often AI assistants recommend a business, plus technical website checks.',
            'Rules: use only the facts given; do not invent numbers, clients, years, addresses; do not promise rankings or results.',
            "Write every value in {$language} (language code {$analysis->language}).",
            'Return only a JSON object, no markdown, with exactly these keys:',
            '{"verdict": string (max 400 characters), "summary": array of 3 to 5 strings (each max 220 characters), "h1": string (max 70), "title": string (max 65), "meta_description": string (max 160)}.',
            'h1, title and meta_description are proposals for the homepage that state the business category and location from the facts.',
            'Facts (JSON):',
            json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * Aggregates only. Check evidence is a factual observation of the public site, not an AI answer.
     *
     * @return array<string, mixed>
     */
    public function facts(Analysis $analysis): array
    {
        $stats = AuditFacts::for($analysis);
        $checks = array_values(array_filter($analysis->siteChecks(), fn (SiteCheck $c) => $c->status->needsAction()));

        return [
            'domain' => $analysis->domain,
            'market' => $analysis->market,
            'location' => $analysis->location,
            'industry' => $analysis->industry,
            'score_percent' => $stats->score(),
            'recommended_tests' => (int) $analysis->recommendation_count,
            'successful_tests' => (int) $analysis->successful_tests,
            'failed_tests_excluded' => (int) $analysis->failed_tests,
            'providers' => array_map(fn ($p) => ['name' => $p['name'], 'recommended' => $p['recommended'], 'successful' => $p['successful'], 'percent' => $p['percentage']], $stats->providers()),
            'categories' => array_map(fn ($c) => ['recommended' => $c['recommended'], 'successful' => $c['successful'], 'percent' => $c['percentage']], $stats->categories()),
            'top_competitors' => $stats->competitors->take(5)->map(fn ($c) => ['name' => $c->name, 'mentions' => (int) $c->mention_count])->values()->all(),
            'checks_needing_action' => array_map(fn (SiteCheck $c) => ['key' => $c->key, 'status' => $c->status->value, 'evidence' => $c->evidence], array_slice($checks, 0, 15)),
        ];
    }

    /** Strict JSON with the expected keys; lengths are clamped, anything else is rejected. */
    public function parse(string $text): ?AuditNarrative
    {
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($data) || ! is_string($data['verdict'] ?? null) || ! is_array($data['summary'] ?? null)) {
            return null;
        }

        $verdict = $this->clamp($data['verdict'], self::LIMITS['verdict']);
        $summary = array_values(array_filter(array_map(
            fn ($item) => is_string($item) ? $this->clamp($item, self::LIMITS['summary_item']) : null,
            $data['summary'],
        )));
        if ($verdict === null || count($summary) < 3) {
            return null;
        }

        $optional = fn (string $key) => is_string($data[$key] ?? null) ? $this->clamp($data[$key], self::LIMITS[$key]) : null;

        return new AuditNarrative($verdict, array_slice($summary, 0, 5), $optional('h1'), $optional('title'), $optional('meta_description'), 'ai');
    }

    /**
     * Deterministic narrative from lang keys `audit.narrative.*` in the analysis language.
     *
     * @param  array<string, mixed>|null  $facts
     */
    public function rules(Analysis $analysis, ?array $facts, bool $demo = false): AuditNarrative
    {
        $facts ??= $this->facts($analysis);
        $lang = $analysis->language;
        $t = fn (string $key, array $params = []) => (string) __("audit.narrative.{$key}", $params, $lang);

        $score = $facts['score_percent'];
        $band = match (true) {
            $score === null => 'none',
            $score < 25 => 'low',
            $score < 60 => 'mid',
            default => 'high',
        };
        $common = ['domain' => $analysis->domain, 'score' => self::number($score ?? 0), 'recommended' => $facts['recommended_tests'], 'successful' => $facts['successful_tests']];
        $verdict = $t("verdict_{$band}", $common);

        $summary = [$t('summary_score', $common)];

        $providers = array_values(array_filter($facts['providers'], fn ($p) => $p['successful'] > 0));
        if (count($providers) >= 2) {
            usort($providers, fn ($a, $b) => $b['percent'] <=> $a['percent']);
            $best = $providers[0];
            $worst = $providers[count($providers) - 1];
            $summary[] = $t('summary_providers', ['best' => $best['name'], 'best_percent' => self::number($best['percent']), 'worst' => $worst['name'], 'worst_percent' => self::number($worst['percent'])]);
        }

        if ($facts['top_competitors'] !== []) {
            $summary[] = $t('summary_competitors', ['competitors' => implode(', ', array_column(array_slice($facts['top_competitors'], 0, 3), 'name'))]);
        }

        $checks = $facts['checks_needing_action'];
        $summary[] = $checks === []
            ? $t('summary_checks_ok')
            : $t('summary_checks', ['count' => count($checks), 'evidence' => implode('; ', array_column(array_slice($checks, 0, 2), 'evidence'))]);

        if ($facts['failed_tests_excluded'] > 0) {
            $summary[] = $t('summary_failed', ['failed' => $facts['failed_tests_excluded']]);
        }
        if (count($summary) < 3) {
            $summary[] = $t('summary_method');
        }
        $summary = array_slice($summary, 0, 5);

        $h1 = $title = $meta = null;
        if (filled($analysis->industry)) {
            $place = $analysis->location ?: (Locale::getDisplayRegion('-'.$analysis->market, $lang) ?: $analysis->market);
            $params = ['industry' => $analysis->industry, 'place' => $place, 'domain' => $analysis->domain];
            $h1 = $this->clamp($t('h1', $params), self::LIMITS['h1']);
            $title = $this->clamp($t('title', $params), self::LIMITS['title']);
            $meta = $this->clamp($t('meta_description', $params), self::LIMITS['meta_description']);
        }

        if ($demo) {
            $prefix = $t('demo_prefix');
            $verdict = $prefix.' '.$verdict;
            $summary = array_map(fn (string $s) => $prefix.' '.$s, $summary);
        }

        return new AuditNarrative(
            (string) $this->clamp($verdict, self::LIMITS['verdict']),
            array_map(fn (string $s) => (string) $this->clamp($s, self::LIMITS['summary_item']), $summary),
            $h1,
            $title,
            $meta,
            $demo ? 'demo' : 'rules',
        );
    }

    private function logUsage(Analysis $analysis, AiProvider $provider, ProviderResult $result, bool $valid): void
    {
        $cost = $provider->estimateCost($result->inputTokens, $result->outputTokens, $result->searchCalls);

        ApiUsageLog::create([
            'analysis_id' => $analysis->id,
            'ai_provider_id' => $provider->id,
            'provider' => $provider->slug->value,
            'model' => $result->model,
            'operation' => 'narrative',
            'status' => $result->successful && $valid ? 'succeeded' : 'failed',
            'is_demo' => false,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'search_calls' => $result->searchCalls,
            'estimated_cost' => $cost,
            'duration_ms' => $result->durationMs,
            'error_code' => $result->errorCode ?? ($valid ? null : 'invalid_response'),
            'created_at' => now(),
        ]);

        if ($cost > 0) {
            Analysis::query()->whereKey($analysis->id)->increment('estimated_cost', $cost);
        }
    }

    private function clamp(string $value, int $max): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)));

        if ($value === '') {
            return null;
        }

        return mb_strlen($value) <= $max ? $value : rtrim(mb_substr($value, 0, $max - 1)).'…';
    }

    private static function number(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
    }
}
