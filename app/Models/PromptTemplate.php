<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'template', 'notes', 'is_enabled', 'sort_order'])]
class PromptTemplate extends Model
{
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    #[Scope]
    protected function enabled(Builder $query): void
    {
        $query->where('is_enabled', true)->orderBy('sort_order')->orderBy('id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AnalysisQuestion::class);
    }
}
