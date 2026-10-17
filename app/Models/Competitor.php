<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['analysis_id', 'name', 'normalized_name', 'domain', 'mention_count', 'providers'])]
class Competitor extends Model
{
    protected function casts(): array
    {
        return ['providers' => 'array'];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }
}
