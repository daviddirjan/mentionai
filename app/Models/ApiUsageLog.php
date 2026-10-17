<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'analysis_id', 'ai_response_id', 'ai_provider_id', 'provider', 'model', 'operation', 'status', 'is_demo',
    'input_tokens', 'output_tokens', 'search_calls', 'estimated_cost', 'duration_ms', 'error_code', 'created_at',
])]
class ApiUsageLog extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'estimated_cost' => 'decimal:6',
            'created_at' => 'datetime',
        ];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }
}
