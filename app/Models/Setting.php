<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Use App\Support\Settings to read and write values; it applies defaults and caching. */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
