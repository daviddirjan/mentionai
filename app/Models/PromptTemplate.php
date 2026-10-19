<?php

namespace App\Models;

use App\Enums\QuestionCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'category', 'template', 'notes', 'is_enabled', 'is_audit_only', 'sort_order'])]
class PromptTemplate extends Model
{
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'is_audit_only' => 'boolean', 'category' => QuestionCategory::class];
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
