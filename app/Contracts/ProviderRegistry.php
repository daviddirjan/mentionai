<?php

namespace App\Contracts;

use App\Models\AiProvider;

interface ProviderRegistry
{
    /**
     * Returns the live adapter, or the mock adapter when config('mentionai.ai_mode') === 'mock'.
     * Live mode never falls back to the mock adapter.
     */
    public function adapterFor(AiProvider $provider): AiProviderAdapter;

    /** Whether results are demo data (mock mode). */
    public function isMockMode(): bool;
}
