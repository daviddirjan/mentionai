<?php

namespace App\Services\Inspection;

/** Outcome of one SafeFetcher::get(). `error` is null only for a 2xx response with an accepted content type. */
final readonly class FetchResult
{
    public function __construct(
        public string $url,
        public string $finalUrl,
        public ?int $status,
        /** @var array<string, string> lower-cased header name => first value */
        public array $headers,
        public ?string $body,
        public int $durationMs,
        public int $redirects,
        public ?string $error,   // unsafe_url, timeout, too_large, too_many_redirects, redirect_without_location, off_host, http_{status}, content_type, fetch_failed
    ) {}

    public function ok(): bool
    {
        return $this->error === null;
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function contentType(): string
    {
        return strtolower(trim(explode(';', $this->header('Content-Type'))[0]));
    }
}
