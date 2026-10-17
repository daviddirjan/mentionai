<?php

namespace App\Contracts;

use App\Data\ConnectionTestResult;
use App\Data\ProviderQuery;
use App\Data\ProviderResult;
use App\Models\AiProvider;

/**
 * One implementation per provider (OpenAI, Gemini, Anthropic, Perplexity) plus a mock adapter.
 * Model id, timeouts and limits come from the AiProvider row; API keys come from config only.
 */
interface AiProviderAdapter
{
    /** Must not throw on API/network errors: return ProviderResult::failure(...). */
    public function query(AiProvider $provider, ProviderQuery $query): ProviderResult;

    public function testConnection(AiProvider $provider): ConnectionTestResult;
}
