<?php

namespace App\Data;

use App\Models\Analysis;

final readonly class AnalysisStartResult
{
    public function __construct(
        public Analysis $analysis,
        /** True when a recent cached analysis (or an in-flight duplicate) was returned instead of a new one. */
        public bool $reused,
    ) {}
}
