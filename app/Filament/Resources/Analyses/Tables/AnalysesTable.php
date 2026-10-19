<?php

namespace App\Filament\Resources\Analyses\Tables;

use App\Contracts\ReportRenderer;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisTier;
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
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AnalysesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('company_name')->label('Company')->searchable()->sortable(),
                TextColumn::make('domain')->label('Website')->searchable(),
                TextColumn::make('tier')->badge()
                    ->formatStateUsing(fn (AnalysisTier $state) => self::tierOptions()[$state->value])
                    ->color(fn (AnalysisTier $state) => $state->isAudit() ? 'primary' : 'gray'),
                TextColumn::make('market')->sortable(),
                TextColumn::make('location')->label('City / region')->placeholder('—')->toggleable(),
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
                SelectFilter::make('tier')->options(self::tierOptions()),
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
                self::downloadReportAction(),
                self::renderReportAction(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn ($livewire) => Csv::download(
                        'analyses-'.now()->format('Ymd-His').'.csv',
                        ['id', 'uuid', 'tier', 'parent_uuid', 'company', 'website', 'market', 'location', 'language', 'industry', 'status', 'score', 'successful_tests', 'failed_tests', 'estimated_cost_usd', 'demo', 'created_at'],
                        $livewire->getFilteredSortedTableQuery()->with('parent:id,uuid'),
                        fn (Analysis $a) => [$a->id, $a->uuid, $a->tier->value, $a->parent?->uuid, $a->company_name, $a->domain, $a->market, $a->location, $a->language, $a->industry, $a->status->value, $a->visibility_score, $a->successful_tests, $a->failed_tests, $a->estimated_cost, $a->is_demo ? 'yes' : 'no', $a->created_at],
                    )),
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    /** @return array<string, string> */
    public static function tierOptions(): array
    {
        return [AnalysisTier::Check->value => 'Check', AnalysisTier::Audit->value => 'GEO Audit'];
    }

    public static function downloadReportAction(): Action
    {
        return Action::make('downloadReport')
            ->label('Download PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->visible(fn (Analysis $record) => $record->hasReport())
            ->action(function (Analysis $record): ?StreamedResponse {
                $disk = Storage::disk('local');

                if (! $disk->exists($record->report_path)) {
                    Notification::make()->danger()->title('Report file is missing')->body('Regenerate the PDF.')->send();

                    return null;
                }

                return $disk->download($record->report_path, self::reportFilename($record), ['Content-Type' => 'application/pdf']);
            });
    }

    public static function renderReportAction(): Action
    {
        return Action::make('renderReport')
            ->label(fn (Analysis $record) => $record->hasReport() ? 'Regenerate PDF' : 'Generate PDF')
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->visible(fn (Analysis $record) => $record->isAudit() && $record->status === AnalysisStatus::Completed)
            ->action(function (Analysis $record): void {
                try {
                    app(ReportRenderer::class)->render($record);
                    Notification::make()->success()->title('PDF report generated')->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('PDF generation failed')->body(class_basename($e))->send();
                }
            });
    }

    public static function reportFilename(Analysis $record): string
    {
        return 'geo-audit-'.$record->domain.'-'.($record->report_generated_at ?? now())->format('Y-m-d').'.pdf';
    }
}
