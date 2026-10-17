<?php

namespace App\Filament\Widgets;

use App\Enums\ResponseStatus;
use App\Models\AiResponse;
use Filament\Widgets\ChartWidget;

class ResponsesByProviderChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Responses by provider';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = AiResponse::query()
            ->whereIn('status', [ResponseStatus::Succeeded->value, ResponseStatus::Failed->value])
            ->selectRaw('provider, status, COUNT(*) as total')
            ->groupBy('provider', 'status')
            ->get();

        $providers = $rows->pluck('provider')->unique()->sort()->values();
        $count = fn (string $provider, ResponseStatus $status) => (int) ($rows->first(
            fn ($r) => $r->provider === $provider && $r->status === $status
        )?->total ?? 0);

        return [
            'datasets' => [
                ['label' => 'Successful', 'data' => $providers->map(fn ($p) => $count($p, ResponseStatus::Succeeded))->all(), 'backgroundColor' => '#34d399'],
                ['label' => 'Failed', 'data' => $providers->map(fn ($p) => $count($p, ResponseStatus::Failed))->all(), 'backgroundColor' => '#f87171'],
            ],
            'labels' => $providers->all(),
        ];
    }
}
