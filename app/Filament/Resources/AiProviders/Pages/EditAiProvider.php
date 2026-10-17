<?php

namespace App\Filament\Resources\AiProviders\Pages;

use App\Filament\Resources\AiProviders\AiProviderResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditAiProvider extends EditRecord
{
    protected static string $resource = AiProviderResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return AiProviderResource::modeBanner();
    }

    protected function getHeaderActions(): array
    {
        return [AiProviderResource::testConnectionAction()];
    }
}
