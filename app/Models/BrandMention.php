<?php

namespace App\Models;

use App\Enums\MentionClassification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['analysis_id', 'ai_response_id', 'classification', 'matched_terms', 'evidence'])]
class BrandMention extends Model
{
    protected function casts(): array
    {
        return [
            'classification' => MentionClassification::class,
            'matched_terms' => 'array',
            'evidence' => 'array',
        ];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(AiResponse::class, 'ai_response_id');
    }
}
