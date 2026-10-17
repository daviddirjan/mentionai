<?php

namespace App\Enums;

enum ProviderSlug: string
{
    case OpenAI = 'openai';
    case Gemini = 'gemini';
    case Anthropic = 'anthropic';
    case Perplexity = 'perplexity';

    /** Consumer-facing assistant name shown on the public site. */
    public function displayName(): string
    {
        return match ($this) {
            self::OpenAI => 'ChatGPT',
            self::Gemini => 'Gemini',
            self::Anthropic => 'Claude',
            self::Perplexity => 'Perplexity',
        };
    }

    /** Environment variable that holds the API key. Keys are never stored in the database. */
    public function apiKeyEnv(): string
    {
        return match ($this) {
            self::OpenAI => 'OPENAI_API_KEY',
            self::Gemini => 'GEMINI_API_KEY',
            self::Anthropic => 'ANTHROPIC_API_KEY',
            self::Perplexity => 'PERPLEXITY_API_KEY',
        };
    }
}
