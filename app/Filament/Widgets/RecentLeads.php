<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentLeads extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest leads')
            ->query(fn () => Lead::query()->latest()->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('company'),
                TextColumn::make('email'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('created_at')->label('Date')->since(),
            ])
            ->recordUrl(fn (Lead $record) => LeadResource::getUrl('view', ['record' => $record]));
    }
}
