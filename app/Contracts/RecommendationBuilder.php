<?php

namespace App\Contracts;

use App\Data\Recommendation;
use App\Models\Analysis;

interface RecommendationBuilder
{
    /**
     * Deterministic rules over the stored site audit and visibility results. No API calls.
     *
     * @return list<Recommendation> sorted P0 → P2
     */
    public function build(Analysis $analysis): array;
}
