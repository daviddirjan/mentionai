<?php

namespace App\Enums;

enum AnalysisStatus: string
{
    case Pending = 'pending';
    case InspectingWebsite = 'inspecting_website';
    case AuditingSite = 'auditing_site';           // audit tier only: technical GEO checks
    case GeneratingQuestions = 'generating_questions';
    case QueryingProviders = 'querying_providers';
    case Analyzing = 'analyzing';
    case Scoring = 'scoring';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }

    /** Translation key for the public progress label, e.g. __('analysis.status.querying_providers'). */
    public function translationKey(): string
    {
        return 'analysis.status.'.$this->value;
    }

    /** Ordered steps shown in the public progress indicator. */
    public static function progressSteps(?AnalysisTier $tier = null): array
    {
        return [
            self::Pending,
            ...($tier === AnalysisTier::Audit ? [self::AuditingSite] : []),
            self::GeneratingQuestions,
            self::QueryingProviders,
            self::Analyzing,
            self::Scoring,
            self::Completed,
        ];
    }
}
