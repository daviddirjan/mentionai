<?php

namespace App\Filament\Support;

use App\Models\Analysis;
use App\Models\ApiUsageLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Aggregates api_usage_logs. Every figure is an estimate derived from configured prices, not an invoice. */
final class CostReport
{
    public function __construct(private readonly bool $includeDemo = false) {}

    private function logs(): Builder
    {
        return ApiUsageLog::query()
            ->where('operation', 'query')
            ->when(! $this->includeDemo, fn (Builder $q) => $q->where('is_demo', false));
    }

    /** @return array{calls: int, failed: int, input_tokens: int, output_tokens: int, cost: float, month_cost: float, today_cost: float} */
    public function totals(): array
    {
        $row = $this->logs()
            ->selectRaw("COUNT(*) as calls, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed, COALESCE(SUM(input_tokens),0) as input_tokens, COALESCE(SUM(output_tokens),0) as output_tokens, COALESCE(SUM(estimated_cost),0) as cost")
            ->first();

        return [
            'calls' => (int) $row->calls,
            'failed' => (int) $row->failed,
            'input_tokens' => (int) $row->input_tokens,
            'output_tokens' => (int) $row->output_tokens,
            'cost' => (float) $row->cost,
            'month_cost' => (float) $this->logs()->where('created_at', '>=', now()->startOfMonth())->sum('estimated_cost'),
            'today_cost' => (float) $this->logs()->where('created_at', '>=', now()->startOfDay())->sum('estimated_cost'),
        ];
    }

    public function averageCostPerAnalysis(): float
    {
        return (float) Analysis::query()
            ->where('status', 'completed')
            ->when(! $this->includeDemo, fn (Builder $q) => $q->where('is_demo', false))
            ->avg('estimated_cost');
    }

    /** @return Collection<int, object{provider: string, calls: int, failed: int, input_tokens: int, output_tokens: int, cost: float}> */
    public function perProvider(): Collection
    {
        return $this->logs()
            ->selectRaw("provider, COUNT(*) as calls, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed, COALESCE(SUM(input_tokens),0) as input_tokens, COALESCE(SUM(output_tokens),0) as output_tokens, COALESCE(SUM(estimated_cost),0) as cost")
            ->groupBy('provider')
            ->orderBy('provider')
            ->get();
    }

    /** @return Collection<string, float> date => cost, newest first, last 30 days */
    public function daily(): Collection
    {
        $rows = $this->logs()
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as day, SUM(estimated_cost) as cost')
            ->groupBy('day')
            ->orderByDesc('day')
            ->pluck('cost', 'day');

        return $rows->map(fn ($c) => (float) $c);
    }

    /** @return array<string, float> "Oct 2026" => cost, last 6 months */
    public function monthly(): array
    {
        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $start = now()->startOfMonth()->subMonths($i);
            $months[$start->format('M Y')] = (float) $this->logs()
                ->whereBetween('created_at', [$start, $start->copy()->endOfMonth()])
                ->sum('estimated_cost');
        }

        return $months;
    }

    /** @return Collection<int, Analysis> */
    public function recentAnalyses(int $limit = 20): Collection
    {
        return Analysis::query()
            ->when(! $this->includeDemo, fn (Builder $q) => $q->where('is_demo', false))
            ->where('estimated_cost', '>', 0)
            ->latest()
            ->limit($limit)
            ->get(['id', 'uuid', 'company_name', 'domain', 'status', 'estimated_cost', 'successful_tests', 'failed_tests', 'created_at']);
    }
}
