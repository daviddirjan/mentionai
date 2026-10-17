<?php

namespace App\Services\Ai\Adapters;

use App\Data\ProviderQuery;
use App\Models\AiProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/** Perplexity Sonar (chat completions). Search is built into the model. */
class PerplexityAdapter extends HttpProviderAdapter
{
    protected function slug(): string
    {
        return 'perplexity';
    }

    protected function authenticate(PendingRequest $http, string $apiKey): PendingRequest
    {
        return $http->withToken($apiKey);
    }

    protected function send(PendingRequest $http, AiProvider $provider, ProviderQuery $query, bool $withSearch, int $maxTokens): Response
    {
        return $http->post($this->baseUrl().'/chat/completions', [
            'model' => $provider->model,
            'messages' => [['role' => 'user', 'content' => $query->question]],
            'max_tokens' => $maxTokens,
        ]);
    }

    protected function normalize(array $json): ?array
    {
        $message = $json['choices'][0]['message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        $citations = [];
        foreach ($json['search_results'] ?? [] as $result) {
            if (! empty($result['url'])) {
                $citations[] = ['url' => $result['url'], 'title' => $result['title'] ?? null];
            }
        }
        foreach ($json['citations'] ?? [] as $url) {
            if (is_string($url)) {
                $citations[] = ['url' => $url, 'title' => null];
            }
        }

        $usage = $json['usage'] ?? [];

        return [
            'text' => (string) ($message['content'] ?? ''),
            'citations' => $this->uniqueCitations($citations),
            'input_tokens' => isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            'output_tokens' => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
            'search_calls' => isset($usage['num_search_queries']) ? (int) $usage['num_search_queries'] : 1,
        ];
    }
}
