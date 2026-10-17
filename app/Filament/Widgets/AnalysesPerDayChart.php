<?php

namespace App\Filament\Widgets;

use App\Filament\Support\Series;
use App\Models\Analysis;
use Filament\Widgets\ChartWidget;

class AnalysesPerDayChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Analyses per day (30 days)';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $series = Series::daily(Analysis::query(), 'created_at');

        return [
            'datasets' => [['label' => 'Analyses', 'data' => $series['values'], 'borderColor' => '#818cf8', 'backgroundColor' => 'rgba(129,140,248,0.2)', 'fill' => true]],
            'labels' => $series['labels'],
        ];
    }
}
