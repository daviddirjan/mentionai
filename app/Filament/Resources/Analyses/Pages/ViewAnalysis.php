<?php

namespace App\Filament\Resources\Analyses\Pages;

use App\Contracts\AnalysisMaintenance;
use App\Enums\ResponseStatus;
use App\Filament\Resources\Analyses\AnalysisResource;
use App\Filament\Resources\Analyses\Tables\AnalysesTable;
use App\Models\Analysis;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewAnalysis extends ViewRecord
{
    protected static string $resource = AnalysisResource::class;

    // Models are strict about lazy loading, so everything the infolist renders is loaded up front.
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load([
            'parent:id,uuid',
            'competitors',
            'usageLogs',
            'questions.responses.brandMention',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryFailed')
                ->label('Retry failed requests')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription(fn (Analysis $record) => 'Only the '.$this->failedCount($record).' failed provider request(s) will be re-queued. Successful responses are never re-run.')
                ->action(function (Analysis $record): void {
                    $count = app(AnalysisMaintenance::class)->retryFailed($record);

                    Notification::make()->success()->title("{$count} failed request(s) re-queued")->send();
                    $this->refreshRecord();
                }),
            Action::make('recalculate')
                ->label('Recalculate score')
                ->icon('heroicon-o-calculator')
                ->requiresConfirmation()
                ->modalDescription('Re-runs brand detection and scoring from the stored responses. No API calls are made.')
                ->action(function (Analysis $record): void {
                    app(AnalysisMaintenance::class)->recalculate($record);

                    Notification::make()->success()->title('Score recalculated')->send();
                    $this->refreshRecord();
                }),
            AnalysesTable::downloadReportAction(),
            AnalysesTable::renderReportAction()->after(fn () => $this->refreshRecord()),
            DeleteAction::make()
                ->modalDescription('This permanently deletes the analysis with all its questions, responses and competitors.')
                ->successRedirectUrl(AnalysisResource::getUrl('index')),
        ];
    }

    private function failedCount(Analysis $analysis): int
    {
        return $analysis->responses()->where('status', ResponseStatus::Failed->value)->count();
    }

    private function refreshRecord(): void
    {
        $this->record = $this->resolveRecord($this->record->getRouteKey());
    }
}
