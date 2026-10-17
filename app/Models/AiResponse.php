<?php

namespace App\Models;

use App\Enums\ResponseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'analysis_id', 'analysis_question_id', 'ai_provider_id', 'provider', 'model', 'status', 'is_demo',
    'raw_response', 'response_text', 'citations', 'duration_ms', 'input_tokens', 'output_tokens',
    'search_calls', 'estimated_cost', 'attempts', 'error_code', 'error_message', 'started_at', 'completed_at',
])]
class AiResponse extends Model
{
    /** raw_response is admin-only; never render it on public pages. */
    protected $hidden = ['raw_response'];

    protected function casts(): array
    {
        return [
            'status' => ResponseStatus::class,
            'is_demo' => 'boolean',
            'raw_response' => 'array',
            'citations' => 'array',
            'estimated_cost' => 'decimal:6',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AnalysisQuestion::class, 'analysis_question_id');
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function brandMention(): HasOne
    {
        return $this->hasOne(BrandMention::class);
    }
}
