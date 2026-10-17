<?php

namespace App\Models;

use App\Enums\ProviderSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug', 'name', 'display_name', 'is_enabled', 'model', 'max_requests_per_analysis', 'timeout_seconds',
    'max_output_tokens', 'max_search_calls', 'input_price_per_million', 'output_price_per_million',
    'search_price_per_thousand', 'sort_order', 'last_test_status', 'last_test_message', 'last_tested_at',
])]
class AiProvider extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'slug' => ProviderSlug::class,
            'is_enabled' => 'boolean',
            'input_price_per_million' => 'decimal:6',
            'output_price_per_million' => 'decimal:6',
            'search_price_per_thousand' => 'decimal:6',
            'last_tested_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function enabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AiResponse::class);
    }

    /** Whether an API key is configured. The key itself never leaves config. */
    public function hasApiKey(): bool
    {
        return filled(config("mentionai.providers.{$this->slug->value}.api_key"));
    }

    /** Estimated USD cost of a call. Estimates only; not provider invoices. */
    public function estimateCost(?int $inputTokens, ?int $outputTokens, ?int $searchCalls): float
    {
        return round(
            ($inputTokens ?? 0) / 1_000_000 * (float) $this->input_price_per_million
            + ($outputTokens ?? 0) / 1_000_000 * (float) $this->output_price_per_million
            + ($searchCalls ?? 0) / 1_000 * (float) $this->search_price_per_thousand,
            6,
        );
    }
}
