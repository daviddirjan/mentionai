<?php

namespace App\Services\Scoring;

use App\Enums\MentionClassification;
use App\Enums\ResponseStatus;
use App\Models\Analysis;

/**
 * Visibility Score = recommended successful responses / successful responses x 100.
 * Failed and skipped responses never enter the denominator.
 */
class ScoreCalculator
{
    /**
     * @return array{
     *   visibility_score: ?float, successful_tests: int, failed_tests: int, recommendation_count: int,
     *   mention_count: int, citation_count: int, provider_scores: array<string, array{recommended: int, successful: int, total: int, percentage: float}>
     * }
     */
    public function calculate(Analysis $analysis): array
    {
        $responses = $analysis->responses()->with('brandMention')->get();

        $scores = [];
        $totals = ['successful' => 0, 'failed' => 0, 'recommended' => 0, 'mentioned' => 0, 'cited' => 0];

        foreach ($responses as $response) {
            $slug = $response->provider;
            $scores[$slug] ??= ['recommended' => 0, 'successful' => 0, 'total' => 0, 'percentage' => 0.0];
            $scores[$slug]['total']++;

            if ($response->status !== ResponseStatus::Succeeded) {
                if ($response->status !== ResponseStatus::Pending && $response->status !== ResponseStatus::Running) {
                    $totals['failed']++;
                }

                continue;
            }

            $scores[$slug]['successful']++;
            $totals['successful']++;

            $mention = $response->brandMention;
            if ($mention === null) {
                continue;
            }

            $class = $mention->classification;
            if ($class->countsAsRecommendation()) {
                $scores[$slug]['recommended']++;
                $totals['recommended']++;
            }
            if (in_array($class, [MentionClassification::Recommended, MentionClassification::Mentioned], true)) {
                $totals['mentioned']++;
            }
            $hasCitation = $class === MentionClassification::CitedOnly
                || collect($mention->evidence ?? [])->contains(fn ($e) => ($e['type'] ?? null) === 'citation');
            if ($hasCitation) {
                $totals['cited']++;
            }
        }

        foreach ($scores as $slug => $score) {
            $scores[$slug]['percentage'] = $score['successful'] > 0 ? round($score['recommended'] / $score['successful'] * 100, 2) : 0.0;
        }

        return [
            'visibility_score' => $totals['successful'] > 0 ? round($totals['recommended'] / $totals['successful'] * 100, 2) : null,
            'successful_tests' => $totals['successful'],
            'failed_tests' => $totals['failed'],
            'recommendation_count' => $totals['recommended'],
            'mention_count' => $totals['mentioned'],
            'citation_count' => $totals['cited'],
            'provider_scores' => $scores,
        ];
    }

    public function apply(Analysis $analysis): Analysis
    {
        $analysis->update($this->calculate($analysis));

        return $analysis;
    }
}
