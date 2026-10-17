<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Public routes resolve analyses by `uuid` only (see getRouteKeyName). Never expose the numeric id.
 */
#[Fillable([
    'company_id', 'company_name', 'website_url', 'domain', 'market', 'language', 'industry', 'interface_locale',
    'status', 'is_demo', 'config_hash', 'website_inspection', 'query_config', 'visibility_score',
    'successful_tests', 'failed_tests', 'recommendation_count', 'mention_count', 'citation_count',
    'provider_scores', 'estimated_cost', 'ip_hash', 'error_message', 'started_at', 'completed_at',
])]
class Analysis extends Model
{
    use HasFactory, HasUuids;

    protected $hidden = ['ip_hash'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** Random v4 UUIDs: public URLs must not be guessable or reveal creation time. */
    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'status' => AnalysisStatus::class,
            'is_demo' => 'boolean',
            'website_inspection' => 'array',
            'query_config' => 'array',
            'provider_scores' => 'array',
            'visibility_score' => 'decimal:2',
            'estimated_cost' => 'decimal:6',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AnalysisQuestion::class)->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AiResponse::class);
    }

    public function brandMentions(): HasMany
    {
        return $this->hasMany(BrandMention::class);
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class)->orderByDesc('mention_count')->orderBy('name');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(ApiUsageLog::class);
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }
}
