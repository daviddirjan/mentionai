<?php

namespace App\Services\Audit;

use App\Data\HistoryComparison;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisTier;
use App\Enums\CheckStatus;
use App\Models\Analysis;

/** Before/after against the previous completed analysis of the same company, tier, market, language and location. */
class HistoryComparer
{
    public function previous(Analysis $analysis): ?Analysis
    {
        if ($analysis->company_id === null) {
            return null;
        }

        return Analysis::query()
            ->where('company_id', $analysis->company_id)
            ->where('tier', ($analysis->tier ?? AnalysisTier::Check)->value)
            ->where('market', $analysis->market)
            ->where('language', $analysis->language)
            ->when($analysis->location === null, fn ($q) => $q->whereNull('location'), fn ($q) => $q->where('location', $analysis->location))
            ->where('status', AnalysisStatus::Completed->value)
            ->whereKeyNot($analysis->getKey())
            ->where('id', '<', $analysis->id)
            ->orderByDesc('id')
            ->first();
    }

    public function compare(Analysis $analysis): ?HistoryComparison
    {
        $previous = $this->previous($analysis);
        if ($previous === null) {
            return null;
        }

        $percent = fn (?array $scores, string $slug): ?float => isset($scores[$slug]) && ($scores[$slug]['successful'] ?? 0) > 0 ? (float) $scores[$slug]['percentage'] : null;
        $before = $previous->provider_scores ?? [];
        $after = $analysis->provider_scores ?? [];
        $providers = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $slug) {
            $providers[$slug] = ['before' => $percent($before, $slug), 'after' => $percent($after, $slug)];
        }

        $names = fn (Analysis $a): array => $a->competitors()->get()->mapWithKeys(fn ($c) => [mb_strtolower($c->normalized_name ?: $c->name) => $c->name])->all();
        $was = $names($previous);
        $now = $names($analysis);

        $statuses = fn (Analysis $a): array => collect($a->siteChecks())->mapWithKeys(fn ($c) => [$c->key => $c->status])->all();
        $fixed = $regressed = [];
        if ($analysis->isAudit()) {
            $old = $statuses($previous);
            foreach ($statuses($analysis) as $key => $status) {
                if (! isset($old[$key])) {
                    continue;
                }
                if ($old[$key]->needsAction() && $status === CheckStatus::Pass) {
                    $fixed[] = $key;
                } elseif ($old[$key] === CheckStatus::Pass && $status->needsAction()) {
                    $regressed[] = $key;
                }
            }
        }

        return new HistoryComparison(
            previousUuid: $previous->uuid,
            previousDate: $previous->completed_at ?? $previous->created_at,
            scoreBefore: $previous->visibility_score === null ? null : (float) $previous->visibility_score,
            scoreAfter: $analysis->visibility_score === null ? null : (float) $analysis->visibility_score,
            providers: $providers,
            competitorsGained: array_values(array_diff_key($now, $was)),
            competitorsLost: array_values(array_diff_key($was, $now)),
            checksFixed: $fixed,
            checksRegressed: $regressed,
        );
    }
}
