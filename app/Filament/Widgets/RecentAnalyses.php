<?php

namespace App\Filament\Widgets;

use App\Enums\AnalysisStatus;
use App\Filament\Resources\Analyses\AnalysisResource;
use App\Models\Analysis;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentAnalyses extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest analyses')
            ->query(fn () => Analysis::query()->latest()->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('company_name')->label('Company'),
                TextColumn::make('domain')->label('Website'),
                TextColumn::make('visibility_score')->label('Score')->suffix('%')->placeholder('n/a'),
                TextColumn::make('status')->badge()
                    ->color(fn (AnalysisStatus $state) => match ($state) {
                        AnalysisStatus::Completed => 'success',
                        AnalysisStatus::Failed => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('is_demo')->label('Demo')->badge()->color('warning')
                    ->state(fn (Analysis $record) => $record->is_demo ? 'DEMO' : null),
                TextColumn::make('created_at')->label('Date')->since(),
            ])
            ->recordUrl(fn (Analysis $record) => AnalysisResource::getUrl('view', ['record' => $record]));
    }
}
