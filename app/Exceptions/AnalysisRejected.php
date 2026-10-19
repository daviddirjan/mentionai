<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by AnalysisStarter. The frontend shows __('analysis.rejected.'.$reason).
 * For domain_limit, $existingUuid points at the most recent analysis for that domain when available.
 */
class AnalysisRejected extends RuntimeException
{
    public const CAPTCHA_FAILED = 'captcha_failed';
    public const RATE_LIMITED = 'rate_limited';       // per-IP limit
    public const DOMAIN_LIMIT = 'domain_limit';       // per-domain free limit
    public const DAILY_LIMIT = 'daily_limit';         // global max analyses per day
    public const BUDGET_EXCEEDED = 'budget_exceeded'; // monthly / per-analysis budget
    public const DISABLED = 'disabled';               // global switch off or no providers enabled
    public const INVALID_URL = 'invalid_url';         // unsafe or unresolvable website
    public const NOT_ELIGIBLE = 'not_eligible';       // audit requested from an analysis that is not a completed free check

    public function __construct(
        public readonly string $reason,
        public readonly ?string $existingUuid = null,
    ) {
        parent::__construct("Analysis rejected: {$reason}");
    }
}
