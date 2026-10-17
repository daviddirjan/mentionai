<?php

namespace App\Filament\Resources\Leads;

use App\Enums\LeadStatus;
use App\Filament\Resources\Analyses\AnalysisResource;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Support\Csv;
use App\Models\Lead;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return collect(LeadStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Lead')->columns(2)->schema([
                Select::make('status')->options(self::statusOptions())->required()->native(false),
                Textarea::make('notes')->label('Internal notes')->rows(6)->maxLength(5000)->columnSpanFull()
                    ->helperText('Admin only. Never shown to the lead.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Lead')->columns(3)->schema([
                TextEntry::make('name'),
                TextEntry::make('company')->placeholder('n/a'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('phone')->placeholder('n/a'),
                TextEntry::make('website')->placeholder('n/a'),
                TextEntry::make('locale'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (LeadStatus $state) => $state->label()),
                TextEntry::make('analysis.company_name')->label('Analysis')->placeholder('none')
                    ->url(fn (Lead $record) => $record->analysis ? AnalysisResource::getUrl('view', ['record' => $record->analysis]) : null),
                TextEntry::make('analysis.visibility_score')->label('Analysis score')->suffix('%')->placeholder('n/a'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('message')->placeholder('none')->columnSpanFull(),
                TextEntry::make('notes')->label('Internal notes')->placeholder('none')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('analysis'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('company')->searchable()->toggleable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('phone')->toggleable(),
                TextColumn::make('website')->toggleable()->searchable(),
                TextColumn::make('analysis.visibility_score')->label('Analysis score')->suffix('%')->placeholder('n/a')
                    ->url(fn (Lead $record) => $record->analysis ? AnalysisResource::getUrl('view', ['record' => $record->analysis]) : null),
                SelectColumn::make('status')->options(self::statusOptions())->selectablePlaceholder(false),
                TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
                Filter::make('has_analysis')->label('Linked to an analysis')->query(fn (Builder $query) => $query->whereNotNull('analysis_id')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn ($livewire) => Csv::download(
                        'leads-'.now()->format('Ymd-His').'.csv',
                        ['uuid', 'name', 'company', 'email', 'phone', 'website', 'status', 'message', 'notes', 'analysis_score', 'created_at'],
                        $livewire->getFilteredSortedTableQuery()->with('analysis'),
                        fn (Lead $l) => [$l->uuid, $l->name, $l->company, $l->email, $l->phone, $l->website, $l->status->value, $l->message, $l->notes, $l->analysis?->visibility_score, $l->created_at],
                    )),
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'view' => ViewLead::route('/{record}'),
            'edit' => EditLead::route('/{record}/edit'),
        ];
    }
}
