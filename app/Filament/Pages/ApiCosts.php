<?php

namespace App\Filament\Pages;

use App\Filament\Support\CostReport;
use App\Models\AiProvider;
use BackedEnum;
use Filament\Forms\Components\Select;
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
            'audit_enabled', 'audit_query_count', 'max_cost_per_audit', 'audit_narrative_provider', 'report_link_days',
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
            Section::make('GEO audit')->columns(3)->schema([
                Toggle::make('audit_enabled')->label('GEO audits enabled')->helperText('Off blocks new GEO audits; free checks are unaffected.')->columnSpanFull(),
                TextInput::make('audit_query_count')->numeric()->integer()->minValue(1)->maxValue(50)->required()
                    ->helperText('Discovery questions per audit (x enabled providers = tests).'),
                TextInput::make('max_cost_per_audit')->numeric()->minValue(0)->required()->prefix('$')->helperText('Estimated USD per audit.'),
                Select::make('audit_narrative_provider')->label('Narrative provider')->options(fn () => $this->narrativeProviderOptions())
                    ->required()->native(false)->helperText('One call per audit writes the verdict and summary.'),
                TextInput::make('report_link_days')->numeric()->integer()->minValue(1)->maxValue(90)->required()->suffix('days')
                    ->helperText('Validity of the signed PDF link sent by email.'),
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
            'audit_enabled' => (bool) $values['audit_enabled'],
            'audit_query_count' => (int) $values['audit_query_count'],
            'max_cost_per_audit' => (float) $values['max_cost_per_audit'],
            'audit_narrative_provider' => (string) $values['audit_narrative_provider'],
            'report_link_days' => (int) $values['report_link_days'],
        ];
    }

    /** Enabled providers; the stored value stays selectable even if its provider was disabled since. */
    private function narrativeProviderOptions(): array
    {
        $options = AiProvider::query()->enabled()->get()
            ->mapWithKeys(fn (AiProvider $p) => [$p->slug->value => $p->display_name ?: $p->name])->all();

        $current = settings('audit_narrative_provider');
        if (filled($current) && ! array_key_exists($current, $options)) {
            $options[$current] = $current.' (disabled)';
        }

        return $options;
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
