<?php

namespace App\Services\Ai;

use App\Contracts\AiProviderAdapter;
use App\Contracts\ProviderRegistry;
use App\Enums\ProviderSlug;
use App\Models\AiProvider;
use App\Services\Ai\Adapters\AnthropicAdapter;
use App\Services\Ai\Adapters\GeminiAdapter;
use App\Services\Ai\Adapters\MockAdapter;
use App\Services\Ai\Adapters\OpenAiAdapter;
use App\Services\Ai\Adapters\PerplexityAdapter;

class DefaultProviderRegistry implements ProviderRegistry
{
    public function adapterFor(AiProvider $provider): AiProviderAdapter
    {
        if ($this->isMockMode()) {
            return new MockAdapter;
        }

        return match ($provider->slug) {
            ProviderSlug::OpenAI => new OpenAiAdapter,
            ProviderSlug::Gemini => new GeminiAdapter,
            ProviderSlug::Anthropic => new AnthropicAdapter,
            ProviderSlug::Perplexity => new PerplexityAdapter,
        };
    }

    /** Anything other than an explicit "live" is treated as mock so a typo can never spend money. */
    public function isMockMode(): bool
    {
        return config('mentionai.ai_mode') !== 'live';
    }
}
