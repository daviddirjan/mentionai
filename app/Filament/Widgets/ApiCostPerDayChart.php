<?php

namespace App\Filament\Widgets;

use App\Filament\Support\Series;
use App\Models\ApiUsageLog;
use Filament\Widgets\ChartWidget;

class ApiCostPerDayChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Estimated API cost per day, USD (30 days)';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $live = Series::daily(ApiUsageLog::where('is_demo', false), 'created_at', 'estimated_cost');
        $demo = Series::daily(ApiUsageLog::where('is_demo', true), 'created_at', 'estimated_cost');

        return [
            'datasets' => [
                ['label' => 'Live (estimated)', 'data' => $live['values'], 'backgroundColor' => '#818cf8'],
                ['label' => 'Demo / mock', 'data' => $demo['values'], 'backgroundColor' => '#64748b'],
            ],
            'labels' => $live['labels'],
        ];
    }
}
