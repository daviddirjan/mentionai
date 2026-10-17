<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'normalized_name', 'domain', 'brand_variations'])]
class Company extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['brand_variations' => 'array'];
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }
}
