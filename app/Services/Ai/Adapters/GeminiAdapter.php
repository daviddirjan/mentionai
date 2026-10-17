<?php

namespace App\Services\Ai\Adapters;

use App\Data\ProviderQuery;
use App\Models\AiProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/** Gemini generateContent with Google Search grounding. The key travels in a header, never in the URL. */
class GeminiAdapter extends HttpProviderAdapter
{
    protected function slug(): string
    {
        return 'gemini';
    }

    protected function authenticate(PendingRequest $http, string $apiKey): PendingRequest
    {
        return $http->withHeaders(['x-goog-api-key' => $apiKey]);
    }

    protected function send(PendingRequest $http, AiProvider $provider, ProviderQuery $query, bool $withSearch, int $maxTokens): Response
    {
        $body = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $query->question]]]],
            'generationConfig' => ['maxOutputTokens' => $maxTokens],
        ];

        if ($withSearch) {
            $body['tools'] = [['google_search' => (object) []]];
        }

        return $http->post($this->baseUrl().'/models/'.rawurlencode((string) $provider->model).':generateContent', $body);
    }

    protected function normalize(array $json): ?array
    {
        $candidate = $json['candidates'][0] ?? null;
        if (! is_array($candidate)) {
            return null;
        }

        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            $text .= (string) ($part['text'] ?? '');
        }

        $citations = [];
        foreach ($candidate['groundingMetadata']['groundingChunks'] ?? [] as $chunk) {
            $web = $chunk['web'] ?? null;
            if (is_array($web) && ! empty($web['uri'])) {
                $citations[] = ['url' => $web['uri'], 'title' => $web['title'] ?? null];
            }
        }

        $queries = $candidate['groundingMetadata']['webSearchQueries'] ?? null;
        $usage = $json['usageMetadata'] ?? [];

        return [
            'text' => $text,
            'citations' => $this->uniqueCitations($citations),
            'input_tokens' => isset($usage['promptTokenCount']) ? (int) $usage['promptTokenCount'] : null,
            'output_tokens' => isset($usage['candidatesTokenCount']) ? (int) $usage['candidatesTokenCount'] + (int) ($usage['thoughtsTokenCount'] ?? 0) : null,
            'search_calls' => is_array($queries) ? count($queries) : null,
        ];
    }
}
