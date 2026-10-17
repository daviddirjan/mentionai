<?php

namespace App\Services\Analysis;

use App\Actions\DispatchProviderQueries;
use App\Contracts\AnalysisMaintenance;
use App\Enums\AnalysisStatus;
use App\Enums\ResponseStatus;
use App\Models\Analysis;

class DefaultAnalysisMaintenance implements AnalysisMaintenance
{
    public function __construct(
        private readonly ResultsProcessor $processor,
        private readonly DispatchProviderQueries $dispatch,
    ) {}

    public function retryFailed(Analysis $analysis): int
    {
        $failed = $analysis->responses()->where('status', ResponseStatus::Failed->value)->pluck('id')->all();
        if ($failed === []) {
            return 0;
        }

        $analysis->responses()->whereIn('id', $failed)->update([
            'status' => ResponseStatus::Pending->value,
            'error_code' => null,
            'error_message' => null,
            'completed_at' => null,
        ]);
        $analysis->update(['status' => AnalysisStatus::QueryingProviders, 'error_message' => null, 'completed_at' => null]);

        ($this->dispatch)($analysis, $failed);

        return count($failed);
    }

    public function recalculate(Analysis $analysis): Analysis
    {
        $this->processor->all($analysis);

        return $analysis->refresh();
    }
}
