<?php

namespace App\Models;

use App\Data\AuditNarrative;
use App\Data\Recommendation;
use App\Data\SiteCheck;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisTier;
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
    'company_id', 'parent_analysis_id', 'tier', 'company_name', 'website_url', 'domain', 'market', 'location', 'language', 'industry', 'interface_locale',
    'status', 'is_demo', 'config_hash', 'website_inspection', 'site_audit', 'recommendations', 'narrative', 'report_path', 'report_generated_at', 'query_config', 'visibility_score',
    'successful_tests', 'failed_tests', 'recommendation_count', 'mention_count', 'citation_count',
    'provider_scores', 'estimated_cost', 'ip_hash', 'error_message', 'started_at', 'completed_at',
])]
class Analysis extends Model
{
    use HasFactory, HasUuids;

    protected $hidden = ['ip_hash', 'report_path'];

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
            'tier' => AnalysisTier::class,
            'is_demo' => 'boolean',
            'website_inspection' => 'array',
            'site_audit' => 'array',
            'recommendations' => 'array',
            'narrative' => 'array',
            'report_generated_at' => 'datetime',
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

    /** The free check an audit was started from. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_analysis_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(self::class, 'parent_analysis_id');
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

    public function isAudit(): bool
    {
        return $this->tier === AnalysisTier::Audit;
    }

    public function hasReport(): bool
    {
        return $this->report_path !== null && $this->report_generated_at !== null;
    }

    /** @return list<SiteCheck> */
    public function siteChecks(): array
    {
        return array_map(SiteCheck::fromArray(...), $this->site_audit ?? []);
    }

    /** @return list<Recommendation> */
    public function recommendationList(): array
    {
        return array_map(Recommendation::fromArray(...), $this->recommendations ?? []);
    }

    public function auditNarrative(): ?AuditNarrative
    {
        return $this->narrative === null ? null : AuditNarrative::fromArray($this->narrative);
    }
}
