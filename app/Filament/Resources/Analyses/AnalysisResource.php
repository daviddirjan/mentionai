<?php

namespace App\Filament\Resources\Analyses;

use App\Enums\AnalysisStatus;
use App\Enums\ProviderSlug;
use App\Filament\Resources\Analyses\Pages\ListAnalyses;
use App\Filament\Resources\Analyses\Pages\ViewAnalysis;
use App\Filament\Resources\Analyses\Schemas\AnalysisInfolist;
use App\Filament\Resources\Analyses\Tables\AnalysesTable;
use App\Models\Analysis;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AnalysisResource extends Resource
{
    protected static ?string $model = Analysis::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'company_name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AnalysisInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnalysesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnalyses::route('/'),
            'view' => ViewAnalysis::route('/{record}'),
        ];
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return collect(AnalysisStatus::cases())->mapWithKeys(fn ($s) => [$s->value => str($s->value)->replace('_', ' ')->title()->toString()])->all();
    }

    /** @return array<string, string> */
    public static function providerOptions(): array
    {
        return collect(ProviderSlug::cases())->mapWithKeys(fn ($s) => [$s->value => $s->displayName()])->all();
    }
}
