<?php

namespace App\Actions;

use App\Jobs\FinalizeAnalysis;
use App\Jobs\QueryProvider;
use App\Models\Analysis;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

/** Queues one QueryProvider job per response id; FinalizeAnalysis runs when the batch ends, even with failures. */
class DispatchProviderQueries
{
    /** @param  list<int>  $responseIds */
    public function __invoke(Analysis $analysis, array $responseIds): void
    {
        $analysisId = $analysis->id;

        if ($responseIds === []) {
            FinalizeAnalysis::dispatch($analysisId);

            return;
        }

        Bus::batch(array_map(fn (int $id) => new QueryProvider($id), $responseIds))
            ->name("analysis-{$analysis->uuid}")
            ->allowFailures()
            ->finally(function (Batch $batch) use ($analysisId) {
                FinalizeAnalysis::dispatch($analysisId);
            })
            ->dispatch();
    }
}
