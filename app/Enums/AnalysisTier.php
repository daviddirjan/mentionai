<?php

namespace App\Enums;

enum AnalysisTier: string
{
    case Check = 'check';   // free: query_count questions, no site audit, no PDF
    case Audit = 'audit';   // GEO audit: audit_query_count questions, site audit, recommendations, PDF

    public function isAudit(): bool
    {
        return $this === self::Audit;
    }
}
