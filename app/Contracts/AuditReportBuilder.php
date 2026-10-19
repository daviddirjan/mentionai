<?php

namespace App\Contracts;

use App\Data\AuditReport;
use App\Models\Analysis;

interface AuditReportBuilder
{
    /** Assembles the report from stored data only (no API calls). Works for completed checks and audits. */
    public function build(Analysis $analysis): AuditReport;
}
