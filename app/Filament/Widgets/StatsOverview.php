<?php

namespace App\Filament\Widgets;

use App\Enums\AnalysisTier;
use App\Models\Analysis;
use App\Models\ApiUsageLog;
use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $demoAnalyses = Analysis::where('is_demo', true)->count();
        $liveCost = (float) ApiUsageLog::where('is_demo', false)->sum('estimated_cost');
        $demoCost = (float) ApiUsageLog::where('is_demo', true)->sum('estimated_cost');
        $average = Analysis::where('status', 'completed')->avg('visibility_score');

        return [
            Stat::make('Total analyses', Analysis::count())
                ->description($demoAnalyses.' demo'),
            Stat::make('Analyses today', Analysis::where('created_at', '>=', now()->startOfDay())->count()),
            Stat::make('Analyses this month', Analysis::where('created_at', '>=', now()->startOfMonth())->count()),
            Stat::make('GEO audits (30 days)', Analysis::where('tier', AnalysisTier::Audit->value)->where('created_at', '>=', now()->subDays(30))->count()),
            Stat::make('Total leads', Lead::count()),
            Stat::make('Leads this month', Lead::where('created_at', '>=', now()->startOfMonth())->count()),
            Stat::make('Average visibility score', $average === null ? 'n/a' : number_format((float) $average, 1).'%'),
            Stat::make('Reports sold', 0)->description('Available after payments launch'),
            Stat::make('Revenue', 0)->description('Available after payments launch'),
            Stat::make('Estimated API expenses', '$'.number_format($liveCost, 2))
                ->description('Estimated, not invoices. Demo/mock: $'.number_format($demoCost, 2).' (excluded)'),
        ];
    }
}
