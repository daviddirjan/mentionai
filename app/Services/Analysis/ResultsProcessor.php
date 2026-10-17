<?php

namespace App\Services\Analysis;

use App\Enums\ResponseStatus;
use App\Models\AiResponse;
use App\Models\Analysis;
use App\Models\ApiUsageLog;
use App\Services\Detection\BrandDetector;
use App\Services\Detection\CompetitorExtractor;
use App\Services\Scoring\ScoreCalculator;

/** Detection -> competitors -> scoring from stored responses. No provider calls. */
class ResultsProcessor
{
    public function __construct(
        private readonly BrandDetector $detector,
        private readonly CompetitorExtractor $competitors,
        private readonly ScoreCalculator $scores,
    ) {}

    public function detect(Analysis $analysis): void
    {
        $company = $analysis->company()->first();
        $variations = $company?->brand_variations ?? [];
        $responses = $analysis->responses()->where('status', ResponseStatus::Succeeded->value)->get();

        foreach ($responses as $response) {
            $result = $this->detector->detect(
                (string) $response->response_text,
                $response->citations ?? [],
                $analysis->company_name,
                $analysis->domain,
                $variations,
            );

            $response->brandMention()->updateOrCreate([], [
                'analysis_id' => $analysis->id,
                'classification' => $result->classification,
                'matched_terms' => $result->matchedTerms,
                'evidence' => $result->evidence,
            ]);
        }

        // Responses that no longer succeeded must not keep a stale detection.
        $analysis->brandMentions()
            ->whereNotIn('ai_response_id', $responses->pluck('id'))
            ->delete();

        $extracted = $this->competitors->extract($responses, $analysis->company_name, $analysis->domain, $variations);
        $analysis->competitors()->delete();
        foreach ($extracted as $competitor) {
            $analysis->competitors()->create($competitor);
        }
    }

    public function score(Analysis $analysis): void
    {
        $this->scores->apply($analysis);

        $analysis->update([
            'estimated_cost' => (float) ApiUsageLog::query()->where('analysis_id', $analysis->id)->where('operation', 'query')->sum('estimated_cost'),
        ]);
    }

    public function all(Analysis $analysis): Analysis
    {
        $this->detect($analysis);
        $this->score($analysis);

        return $analysis;
    }
}
