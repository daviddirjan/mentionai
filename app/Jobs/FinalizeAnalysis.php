<?php

namespace App\Jobs;

use App\Contracts\AuditNarrator;
use App\Contracts\RecommendationBuilder;
use App\Data\Recommendation;
use App\Enums\AnalysisStatus;
use App\Models\Analysis;
use App\Services\Analysis\ResultsProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Steps 4-6: detection, competitors, scoring, final status. Failed only when no provider response succeeded.
 * Audits also get recommendations and a narrative before completion, then the PDF job.
 */
class FinalizeAnalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $analysisId) {}

    public function handle(ResultsProcessor $processor, RecommendationBuilder $recommendations, AuditNarrator $narrator): void
    {
        $analysis = Analysis::query()->find($this->analysisId);
        if ($analysis === null) {
            return;
        }

        $analysis->update(['status' => AnalysisStatus::Analyzing]);
        $processor->detect($analysis);

        $analysis->update(['status' => AnalysisStatus::Scoring]);
        $processor->score($analysis);

        $analysis->refresh();
        $succeeded = $analysis->successful_tests > 0;

        if ($succeeded && $analysis->isAudit()) {
            $this->finalizeAudit($analysis, $recommendations, $narrator);
        }

        $analysis->update([
            'status' => $succeeded ? AnalysisStatus::Completed : AnalysisStatus::Failed,
            'error_message' => $succeeded ? null : 'No AI provider returned a usable answer.',
            'completed_at' => now(),
        ]);

        if ($succeeded && $analysis->isAudit()) {
            GenerateAuditReport::dispatch($analysis->id);
        }
    }

    /** Rules first (deterministic), then the narrative, which may read the recommendations. */
    private function finalizeAudit(Analysis $analysis, RecommendationBuilder $recommendations, AuditNarrator $narrator): void
    {
        try {
            $analysis->update(['recommendations' => array_map(fn (Recommendation $r) => $r->toArray(), $recommendations->build($analysis))]);
        } catch (Throwable $e) {
            report($e);
        }

        // The narrator never throws by contract; the report builder falls back to a rules narrative when this is null.
        try {
            $analysis->update(['narrative' => $narrator->narrate($analysis->refresh())->toArray()]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function failed(Throwable $e): void
    {
        $analysis = Analysis::query()->find($this->analysisId);
        if ($analysis !== null && ! $analysis->isFinished()) {
            $analysis->update([
                'status' => AnalysisStatus::Failed,
                'error_message' => Str::limit('Finalization error: '.$e->getMessage(), 500),
                'completed_at' => now(),
            ]);
        }
        report($e);
    }
}
