<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['analysis_id', 'prompt_template_id', 'position', 'question', 'template_snapshot'])]
class AnalysisQuestion extends Model
{
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AiResponse::class);
    }
}
