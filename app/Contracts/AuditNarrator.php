<?php

namespace App\Contracts;

use App\Data\AuditNarrative;
use App\Models\Analysis;

interface AuditNarrator
{
    /**
     * Verdict, summary and H1/title/meta proposal in the analysis language, grounded only in aggregated facts
     * of $analysis (scores, checks, competitors, location). Mock mode → source "demo"; AI failure → source "rules".
     * Never throws.
     */
    public function narrate(Analysis $analysis): AuditNarrative;
}
