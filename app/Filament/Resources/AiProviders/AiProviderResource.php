<?php

namespace App\Filament\Resources\AiProviders;

use App\Contracts\ProviderRegistry;
use App\Filament\Resources\AiProviders\Pages\EditAiProvider;
use App\Filament\Resources\AiProviders\Pages\ListAiProviders;
use App\Models\AiProvider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

class AiProviderResource extends Resource
{
    protected static ?string $model = AiProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'AI';

    protected static ?string $navigationLabel = 'AI providers';

    protected static ?string $modelLabel = 'AI provider';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** Prominent AI mode indicator. API keys are never part of it. */
    public static function modeBanner(): HtmlString
    {
        $live = config('mentionai.ai_mode') === 'live';
        $label = $live ? 'LIVE: real provider calls, costs apply' : 'MOCK: demo data, no paid API calls';
        $color = $live ? '#16a34a' : '#d97706';

        return new HtmlString('<span style="display:inline-block;padding:0.25rem 0.75rem;border-radius:9999px;font-weight:600;color:#fff;background:'.$color.'">AI mode: '.e($label).'</span>');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Provider')->columns(2)->schema([
                TextInput::make('name')->disabled()->dehydrated(false),
                TextInput::make('display_name')->required()->maxLength(60),
                Toggle::make('is_enabled')->label('Enabled'),
                TextInput::make('sort_order')->numeric()->integer()->minValue(0)->required(),
                TextInput::make('model')->required()->maxLength(120),
            ]),
            Section::make('Limits')->columns(2)->schema([
                TextInput::make('max_requests_per_analysis')->numeric()->integer()->minValue(0)->maxValue(100)->required(),
                TextInput::make('timeout_seconds')->numeric()->integer()->minValue(1)->maxValue(300)->required()->suffix('s'),
                TextInput::make('max_output_tokens')->numeric()->integer()->minValue(1)->maxValue(100000)->required(),
                TextInput::make('max_search_calls')->numeric()->integer()->minValue(0)->maxValue(50)
                    ->helperText('Leave empty when the provider has no separate search tool.'),
            ]),
            Section::make('Prices (used for cost estimates only)')->columns(3)->schema([
                TextInput::make('input_price_per_million')->numeric()->minValue(0)->required()->prefix('$')
                    ->helperText('USD per 1M tokens'),
                TextInput::make('output_price_per_million')->numeric()->minValue(0)->required()->prefix('$')
                    ->helperText('USD per 1M tokens'),
                TextInput::make('search_price_per_thousand')->numeric()->minValue(0)->required()->prefix('$')
                    ->helperText('USD per 1K searches'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->paginated(false)
            ->columns([
                TextColumn::make('display_name')->label('Provider')->description(fn (AiProvider $record) => $record->name),
                ToggleColumn::make('is_enabled')->label('Enabled'),
                TextColumn::make('model'),
                IconColumn::make('api_key')->label('Key configured')->boolean()
                    ->state(fn (AiProvider $record) => $record->hasApiKey()),
                TextColumn::make('last_test_status')->label('Last test')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'ok' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })->placeholder('never'),
                TextColumn::make('last_tested_at')->label('Tested at')->dateTime()->placeholder('n/a'),
            ])
            ->recordActions([
                self::testConnectionAction(),
                EditAction::make(),
            ]);
    }

    public static function testConnectionAction(): Action
    {
        return Action::make('testConnection')
            ->label('Test connection')
            ->icon('heroicon-o-signal')
            ->action(function (AiProvider $record): void {
                try {
                    $result = app(ProviderRegistry::class)->adapterFor($record)->testConnection($record);
                } catch (Throwable $e) {
                    Notification::make()->danger()->title('Connection test crashed')->body(class_basename($e))->send();

                    return;
                }

                Notification::make()
                    ->title($result->ok ? 'Connection OK' : 'Connection failed')
                    ->body($result->message.' ('.$result->durationMs.' ms)')
                    ->color($result->ok ? 'success' : 'danger')
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiProviders::route('/'),
            'edit' => EditAiProvider::route('/{record}/edit'),
        ];
    }
}
