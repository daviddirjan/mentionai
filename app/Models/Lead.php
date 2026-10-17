<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'analysis_id', 'name', 'company', 'email', 'phone', 'website', 'message', 'status', 'notes', 'locale', 'ip_hash',
])]
class Lead extends Model
{
    use HasFactory, HasUuids;

    protected $hidden = ['ip_hash', 'notes'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** Random v4 UUIDs: public URLs must not be guessable or reveal creation time. */
    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    protected function casts(): array
    {
        return ['status' => LeadStatus::class];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }
}
