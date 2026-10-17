<?php

namespace App\Filament\Resources\ContactMessages;

use App\Enums\ContactMessageStatus;
use App\Filament\Resources\ContactMessages\Pages\EditContactMessage;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = ContactMessage::whereNull('read_at')->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Unread messages';
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return collect(ContactMessageStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Message')->columns(2)->schema([
                Select::make('status')->options(self::statusOptions())->required()->native(false),
                Textarea::make('notes')->label('Internal notes')->rows(6)->maxLength(5000)->columnSpanFull()
                    ->helperText('Admin only.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Message')->columns(3)->schema([
                TextEntry::make('name'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('company')->placeholder('n/a'),
                TextEntry::make('subject')->columnSpan(2),
                TextEntry::make('status')->badge()->formatStateUsing(fn (ContactMessageStatus $state) => $state->label()),
                TextEntry::make('message')->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-wrap']),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('read_at')->dateTime()->placeholder('unread'),
                TextEntry::make('locale'),
                TextEntry::make('notes')->label('Internal notes')->placeholder('none')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                IconColumn::make('read_at')->label('Read')->boolean()
                    ->state(fn (ContactMessage $record) => $record->read_at !== null),
                TextColumn::make('name')->searchable()->weight(fn (ContactMessage $record) => $record->read_at ? null : 'bold'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('subject')->searchable()->limit(50),
                TextColumn::make('status')->badge()->formatStateUsing(fn (ContactMessageStatus $state) => $state->label()),
                TextColumn::make('created_at')->label('Received')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::statusOptions()),
                TernaryFilter::make('read')->label('Read')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('read_at'),
                        false: fn (Builder $query) => $query->whereNull('read_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::toggleReadAction(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markRead')->label('Mark as read')->icon('heroicon-o-envelope-open')
                        ->action(fn (Collection $records) => $records->each->update(['read_at' => now()])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function toggleReadAction(): Action
    {
        return Action::make('toggleRead')
            ->label(fn (ContactMessage $record) => $record->read_at ? 'Mark unread' : 'Mark read')
            ->icon(fn (ContactMessage $record) => $record->read_at ? 'heroicon-o-envelope' : 'heroicon-o-envelope-open')
            ->action(fn (ContactMessage $record) => $record->update(['read_at' => $record->read_at ? null : now()]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
            'edit' => EditContactMessage::route('/{record}/edit'),
        ];
    }
}
