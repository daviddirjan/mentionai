<?php

namespace App\Services\Ai\Adapters;

use App\Contracts\AiProviderAdapter;
use App\Data\ConnectionTestResult;
use App\Data\ProviderQuery;
use App\Data\ProviderResult;
use App\Models\AiProvider;
use App\Models\ApiUsageLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared HTTP plumbing for live adapters: credentials, timing, error mapping and secret redaction.
 * Subclasses only build the request and normalize the successful JSON payload.
 */
abstract class HttpProviderAdapter implements AiProviderAdapter
{
    abstract protected function slug(): string;

    /** Applies auth headers to the pending request. */
    abstract protected function authenticate(PendingRequest $http, string $apiKey): PendingRequest;

    abstract protected function send(PendingRequest $http, AiProvider $provider, ProviderQuery $query, bool $withSearch, int $maxTokens): Response;

    /**
     * @param  array<string, mixed>  $json
     * @return array{text: string, citations: list<array{url: string, title?: string|null}>, input_tokens: ?int, output_tokens: ?int, search_calls: ?int}|null null when the payload has an unexpected shape
     */
    abstract protected function normalize(array $json): ?array;

    public function query(AiProvider $provider, ProviderQuery $query): ProviderResult
    {
        return $this->execute($provider, $query, true, $provider->max_output_tokens);
    }

    public function testConnection(AiProvider $provider): ConnectionTestResult
    {
        $result = $this->execute($provider, new ProviderQuery('Reply with the single word OK.', 'en', 'US'), false, 512);

        $ok = $result->successful;
        $message = $ok ? 'Connection successful.' : ($result->errorMessage ?? 'Connection failed.');

        $provider->forceFill([
            'last_test_status' => $ok ? 'ok' : 'failed',
            'last_test_message' => Str::limit($message, 500),
            'last_tested_at' => now(),
        ])->save();

        ApiUsageLog::create([
            'ai_provider_id' => $provider->id,
            'provider' => $this->slug(),
            'model' => $provider->model,
            'operation' => 'test_connection',
            'status' => $ok ? 'succeeded' : 'failed',
            'is_demo' => false,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'search_calls' => $result->searchCalls,
            'estimated_cost' => $provider->estimateCost($result->inputTokens, $result->outputTokens, $result->searchCalls),
            'duration_ms' => $result->durationMs,
            'error_code' => $result->errorCode,
            'created_at' => now(),
        ]);

        return new ConnectionTestResult($ok, $message, $result->durationMs);
    }

    private function execute(AiProvider $provider, ProviderQuery $query, bool $withSearch, int $maxTokens): ProviderResult
    {
        $slug = $this->slug();
        $model = (string) $provider->model;
        $apiKey = (string) config("mentionai.providers.{$slug}.api_key");

        if ($apiKey === '') {
            return ProviderResult::failure($slug, $model, 'missing_credentials', 'API key is not configured for this provider.');
        }

        $started = microtime(true);
        $elapsed = fn (): int => (int) round((microtime(true) - $started) * 1000);

        try {
            $http = Http::acceptJson()
                ->asJson()
                ->timeout(max(1, (int) $provider->timeout_seconds))
                ->connectTimeout(min(10, max(1, (int) $provider->timeout_seconds)));
            $response = $this->send($this->authenticate($http, $apiKey), $provider, $query, $withSearch, $maxTokens);
        } catch (ConnectionException $e) {
            $timedOut = Str::contains(strtolower($e->getMessage()), ['timed out', 'timeout', 'error 28']);

            return ProviderResult::failure(
                $slug,
                $model,
                $timedOut ? 'timeout' : 'connection_error',
                $timedOut ? 'The provider did not respond in time.' : 'Could not connect to the provider.',
                $elapsed(),
            );
        } catch (Throwable $e) {
            return ProviderResult::failure($slug, $model, 'connection_error', $this->redact('Request failed: '.$e->getMessage(), $apiKey), $elapsed());
        }

        $duration = $elapsed();

        if ($response->failed()) {
            return $this->httpFailure($slug, $model, $response, $apiKey, $duration);
        }

        $json = $response->json();
        $normalized = is_array($json) ? $this->safeNormalize($json) : null;

        if ($normalized === null || trim($normalized['text']) === '') {
            $reason = is_array($json) ? ($json['incomplete_details']['reason'] ?? null) : null;
            $message = is_string($reason)
                ? "The provider stopped before answering ({$reason}). Raise max output tokens for this provider."
                : 'The provider returned an unexpected or empty response.';

            return ProviderResult::failure($slug, $model, 'invalid_response', $message, $duration, is_array($json) ? $json : null);
        }

        return new ProviderResult(
            provider: $slug,
            model: $model,
            successful: true,
            text: $normalized['text'],
            citations: $normalized['citations'],
            raw: $json,
            durationMs: $duration,
            inputTokens: $normalized['input_tokens'],
            outputTokens: $normalized['output_tokens'],
            searchCalls: $normalized['search_calls'],
        );
    }

    private function safeNormalize(array $json): ?array
    {
        try {
            return $this->normalize($json);
        } catch (Throwable) {
            return null;
        }
    }

    private function httpFailure(string $slug, string $model, Response $response, string $apiKey, int $duration): ProviderResult
    {
        $status = $response->status();
        [$code, $label] = match (true) {
            in_array($status, [401, 403], true) => ['auth_error', 'The provider rejected the credentials'],
            $status === 429 => ['rate_limited', 'The provider rate limit was reached'],
            $status >= 500 => ['provider_error', 'The provider had a temporary error'],
            default => ['provider_error', 'The provider rejected the request'],
        };

        $detail = $response->json('error.message') ?? $response->json('error') ?? $response->json('message');
        $message = "{$label} (HTTP {$status})".(is_string($detail) && $detail !== '' ? ': '.Str::limit($detail, 300) : '.');

        return ProviderResult::failure($slug, $model, $code, $this->redact($message, $apiKey), $duration);
    }

    protected function redact(string $text, string $apiKey): string
    {
        return $apiKey === '' ? $text : str_replace($apiKey, '[redacted]', $text);
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config("mentionai.providers.{$this->slug()}.base_url"), '/');
    }

    /**
     * @param  list<array{url: string, title?: string|null}>  $citations
     * @return list<array{url: string, title?: string|null}>
     */
    protected function uniqueCitations(array $citations): array
    {
        $seen = [];
        $out = [];
        foreach ($citations as $citation) {
            $url = $citation['url'] ?? null;
            if (! is_string($url) || $url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $out[] = ['url' => $url, 'title' => isset($citation['title']) && is_string($citation['title']) ? $citation['title'] : null];
        }

        return $out;
    }
}
