<?php

namespace App\Services\Analysis;

use App\Models\Analysis;
use App\Models\ApiUsageLog;

/** Central place for "may we spend money on this call?" decisions. Costs are estimates from api_usage_logs. */
class SpendingGuard
{
    public function monthlySpend(): float
    {
        return (float) ApiUsageLog::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('estimated_cost');
    }

    public function monthlyBudgetExceeded(): bool
    {
        return $this->monthlySpend() >= (float) settings('monthly_budget');
    }

    /** Error code explaining why a provider call must be skipped, or null when it may proceed. */
    public function blockReason(Analysis $analysis): ?string
    {
        if (! settings('analyses_enabled')) {
            return 'analyses_disabled';
        }

        if ($this->monthlyBudgetExceeded()) {
            return 'budget_exceeded';
        }

        $spent = (float) Analysis::query()->whereKey($analysis->getKey())->value('estimated_cost');
        if ($spent >= $this->maxCostFor($analysis)) {
            return 'budget_exceeded';
        }

        return null;
    }

    /** Per-analysis cost cap: audits run ~8x the calls of a free check and have their own cap. */
    public function maxCostFor(Analysis $analysis): float
    {
        return (float) settings($analysis->isAudit() ? 'max_cost_per_audit' : 'max_cost_per_analysis');
    }
}
