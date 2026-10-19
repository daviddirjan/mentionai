<?php

namespace App\Contracts;

use App\Models\Analysis;

interface ReportRenderer
{
    /**
     * Renders view `pdf.geo-audit` (variable `$report`: AuditReport) to a PDF on the local disk,
     * stores report_path / report_generated_at and returns the path relative to the local disk.
     */
    public function render(Analysis $analysis): string;
}
