<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['is_admin'] = true;

        if ($this->record->is(auth()->user())) {
            $data['is_active'] = true;
        }

        return $data;
    }
}
