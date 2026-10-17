<?php

namespace App\Filament\Pages;

use App\Filament\Support\CostReport;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ApiCosts extends SettingsFormPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'API costs and limits';

    protected static ?string $navigationLabel = 'API costs';

    protected static ?string $slug = 'api-costs';

    protected string $view = 'filament.pages.api-costs';

    public bool $includeDemo = false;

    protected function keys(): array
    {
        return [
            'analyses_enabled', 'max_analyses_per_day', 'max_cost_per_analysis', 'monthly_budget',
            'free_analyses_per_domain', 'domain_window_hours', 'ip_analyses_per_hour', 'cache_hours', 'query_count',
        ];
    }

    protected function fields(): array
    {
        return [
            Section::make('Limits')->columns(3)->schema([
                Toggle::make('analyses_enabled')->label('Analyses enabled')->helperText('Global switch: off blocks all new analyses.')->columnSpanFull(),
                TextInput::make('max_analyses_per_day')->numeric()->integer()->minValue(0)->required(),
                TextInput::make('max_cost_per_analysis')->numeric()->minValue(0)->required()->prefix('$')->helperText('Estimated USD per analysis.'),
                TextInput::make('monthly_budget')->numeric()->minValue(0)->required()->prefix('$')->helperText('Estimated USD per month.'),
                TextInput::make('free_analyses_per_domain')->numeric()->integer()->minValue(0)->required(),
                TextInput::make('domain_window_hours')->numeric()->integer()->minValue(1)->required()->suffix('hours'),
                TextInput::make('ip_analyses_per_hour')->numeric()->integer()->minValue(0)->required(),
                TextInput::make('cache_hours')->numeric()->integer()->minValue(0)->required()->suffix('hours')
                    ->helperText('Identical recent analyses are reused instead of re-queried.'),
                TextInput::make('query_count')->numeric()->integer()->minValue(1)->maxValue(10)->required()
                    ->helperText('Discovery questions per analysis.'),
            ]),
        ];
    }

    protected function toSettings(array $state): array
    {
        $values = parent::toSettings($state);

        return [
            'analyses_enabled' => (bool) $values['analyses_enabled'],
            'max_analyses_per_day' => (int) $values['max_analyses_per_day'],
            'max_cost_per_analysis' => (float) $values['max_cost_per_analysis'],
            'monthly_budget' => (float) $values['monthly_budget'],
            'free_analyses_per_domain' => (int) $values['free_analyses_per_domain'],
            'domain_window_hours' => (int) $values['domain_window_hours'],
            'ip_analyses_per_hour' => (int) $values['ip_analyses_per_hour'],
            'cache_hours' => (int) $values['cache_hours'],
            'query_count' => (int) $values['query_count'],
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $report = new CostReport($this->includeDemo);

        return [
            'totals' => $report->totals(),
            'average' => $report->averageCostPerAnalysis(),
            'providers' => $report->perProvider(),
            'daily' => $report->daily(),
            'monthly' => $report->monthly(),
            'analyses' => $report->recentAnalyses(),
        ];
    }
}
