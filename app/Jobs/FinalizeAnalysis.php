<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Models\Analysis;
use App\Services\Analysis\ResultsProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/** Steps 4-6: detection, competitors, scoring, final status. Failed only when no provider response succeeded. */
class FinalizeAnalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $analysisId) {}

    public function handle(ResultsProcessor $processor): void
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

        $analysis->update([
            'status' => $succeeded ? AnalysisStatus::Completed : AnalysisStatus::Failed,
            'error_message' => $succeeded ? null : 'No AI provider returned a usable answer.',
            'completed_at' => now(),
        ]);
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
