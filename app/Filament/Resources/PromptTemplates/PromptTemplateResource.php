<?php

namespace App\Filament\Resources\PromptTemplates;

use App\Filament\Resources\PromptTemplates\Pages\CreatePromptTemplate;
use App\Filament\Resources\PromptTemplates\Pages\EditPromptTemplate;
use App\Filament\Resources\PromptTemplates\Pages\ListPromptTemplates;
use App\Models\PromptTemplate;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PromptTemplateResource extends Resource
{
    protected static ?string $model = PromptTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'AI';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Template')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('sort_order')->numeric()->integer()->minValue(0)->default(0)->required(),
                Textarea::make('template')->required()->rows(4)->maxLength(1000)->columnSpanFull()
                    ->helperText('Available placeholders: {industry} {service} {market} {city} {language}. The company name must never appear in a discovery question.')
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (is_string($value) && stripos($value, '{company}') !== false) {
                                $fail('The template must not contain {company}: discovery questions never include the company name.');
                            }
                        },
                    ]),
                Toggle::make('is_enabled')->label('Enabled')->default(true),
                Textarea::make('notes')->rows(2)->maxLength(1000)->columnSpanFull()
                    ->helperText('Editing a template never changes past analyses: each question stores a snapshot.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('questions'))
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('template')->limit(80)->wrap(),
                ToggleColumn::make('is_enabled')->label('Enabled'),
                TextColumn::make('questions_count')->label('Past questions')->sortable(),
            ])
            ->filters([TernaryFilter::make('is_enabled')->label('Enabled')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromptTemplates::route('/'),
            'create' => CreatePromptTemplate::route('/create'),
            'edit' => EditPromptTemplate::route('/{record}/edit'),
        ];
    }
}
