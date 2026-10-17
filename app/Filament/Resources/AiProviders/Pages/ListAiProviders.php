<?php

namespace App\Filament\Resources\AiProviders\Pages;

use App\Filament\Resources\AiProviders\AiProviderResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListAiProviders extends ListRecords
{
    protected static string $resource = AiProviderResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return AiProviderResource::modeBanner();
    }
}
