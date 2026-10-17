<?php

namespace App\Contracts;

use App\Models\Analysis;

/** Admin operations on existing analyses. */
interface AnalysisMaintenance
{
    /**
     * Re-queues only failed provider responses (never succeeded ones, so no duplicate paid calls),
     * then re-runs detection and scoring. Returns the number of responses re-queued.
     */
    public function retryFailed(Analysis $analysis): int;

    /** Re-runs brand detection, competitor extraction and scoring from stored responses. No API calls. */
    public function recalculate(Analysis $analysis): Analysis;
}
