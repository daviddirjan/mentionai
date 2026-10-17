<?php

namespace App\Contracts;

use App\Data\AnalysisInput;
use App\Data\AnalysisStartResult;
use App\Exceptions\AnalysisRejected;

interface AnalysisStarter
{
    /**
     * Applies Turnstile, rate limits, spending limits, cache reuse and duplicate prevention,
     * then creates the Analysis and dispatches the background pipeline.
     *
     * @throws AnalysisRejected when the request may not start a new analysis
     */
    public function start(AnalysisInput $input): AnalysisStartResult;
}
