<?php

namespace App\Filament\Resources\Analyses\Tables;

use App\Enums\AnalysisStatus;
use App\Filament\Resources\Analyses\AnalysisResource;
use App\Filament\Support\Csv;
use App\Models\Analysis;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnalysesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('company_name')->label('Company')->searchable()->sortable(),
                TextColumn::make('domain')->label('Website')->searchable(),
                TextColumn::make('market')->sortable(),
                TextColumn::make('language')->sortable(),
                TextColumn::make('visibility_score')->label('Score')->numeric(1)->suffix('%')->sortable()->placeholder('n/a'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => AnalysisResource::statusOptions()[$state->value] ?? $state->value)
                    ->color(fn (AnalysisStatus $state) => match ($state) {
                        AnalysisStatus::Completed => 'success',
                        AnalysisStatus::Failed => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('is_demo')->label('Demo')->badge()->color('warning')
                    ->state(fn (Analysis $record) => $record->is_demo ? 'DEMO' : null),
                TextColumn::make('created_at')->label('Date')->dateTime()->sortable(),
            ])
            ->filters([
                Filter::make('created')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
                SelectFilter::make('market')
                    ->options(fn () => Analysis::query()->distinct()->orderBy('market')->pluck('market', 'market')->all()),
                SelectFilter::make('provider')->label('Has responses from')
                    ->options(AnalysisResource::providerOptions())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('responses', fn ($q) => $q->where('provider', $data['value']))
                        : $query),
                SelectFilter::make('status')->options(AnalysisResource::statusOptions()),
                Filter::make('score')
                    ->schema([
                        TextInput::make('min')->numeric()->minValue(0)->maxValue(100)->label('Min score'),
                        TextInput::make('max')->numeric()->minValue(0)->maxValue(100)->label('Max score'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(filled($data['min'] ?? null), fn ($q) => $q->where('visibility_score', '>=', $data['min']))
                        ->when(filled($data['max'] ?? null), fn ($q) => $q->where('visibility_score', '<=', $data['max']))),
                TernaryFilter::make('is_demo')->label('Demo data'),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn ($livewire) => Csv::download(
                        'analyses-'.now()->format('Ymd-His').'.csv',
                        ['id', 'uuid', 'company', 'website', 'market', 'language', 'industry', 'status', 'score', 'successful_tests', 'failed_tests', 'estimated_cost_usd', 'demo', 'created_at'],
                        $livewire->getFilteredSortedTableQuery(),
                        fn (Analysis $a) => [$a->id, $a->uuid, $a->company_name, $a->domain, $a->market, $a->language, $a->industry, $a->status->value, $a->visibility_score, $a->successful_tests, $a->failed_tests, $a->estimated_cost, $a->is_demo ? 'yes' : 'no', $a->created_at],
                    )),
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
