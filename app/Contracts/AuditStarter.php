<?php

namespace App\Contracts;

use App\Data\AnalysisStartResult;
use App\Exceptions\AnalysisRejected;
use App\Models\Analysis;
use App\Models\Lead;

interface AuditStarter
{
    /**
     * Starts a GEO audit (tier audit) from a completed free check, for a lead that left an email.
     * Copies company, URL, market, language, location and industry from $check; applies audit_enabled,
     * IP/daily/budget limits and cache reuse (config_hash includes the tier). Links the lead to the audit.
     *
     * @throws AnalysisRejected NOT_ELIGIBLE when $check is not a completed check; other reasons as AnalysisStarter
     */
    public function start(Analysis $check, Lead $lead, string $ip): AnalysisStartResult;
}
