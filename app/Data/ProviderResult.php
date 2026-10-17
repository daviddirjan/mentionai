<?php

namespace App\Data;

/**
 * Normalized output of every provider adapter. Adapters must never throw for provider/API errors;
 * they return a failed result instead so one provider cannot break an analysis.
 */
final readonly class ProviderResult
{
    /**
     * @param  list<array{url: string, title?: string|null}>  $citations
     */
    public function __construct(
        public string $provider,
        public string $model,
        public bool $successful,
        public ?string $text = null,
        public array $citations = [],
        public ?array $raw = null,
        public int $durationMs = 0,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?int $searchCalls = null,
        public ?string $errorCode = null,   // e.g. missing_credentials, timeout, http_429, http_5xx, invalid_response
        public ?string $errorMessage = null, // safe for admin display; must never contain API keys
        public bool $isDemo = false,
    ) {}

    public static function failure(string $provider, string $model, string $errorCode, string $errorMessage, int $durationMs = 0, ?array $raw = null): self
    {
        return new self(
            provider: $provider,
            model: $model,
            successful: false,
            raw: $raw,
            durationMs: $durationMs,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
        );
    }
}
