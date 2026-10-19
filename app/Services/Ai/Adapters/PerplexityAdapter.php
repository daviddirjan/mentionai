<?php

namespace App\Services\Ai\Adapters;

use App\Data\ProviderQuery;
use App\Models\AiProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Perplexity Agent API (POST /v1/agent), which replaced Sonar chat completions.
 * The model column holds either a preset ("fast" is the Sonar equivalent) or a "provider/model" id.
 */
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
        $model = (string) $provider->model;
        $body = [
            str_contains($model, '/') ? 'model' : 'preset' => $model,
            'input' => $query->question,
            'max_output_tokens' => $maxTokens,
        ];

        if ($withSearch) {
            $tool = ['type' => 'web_search'];
            if (preg_match('/^[A-Za-z]{2}$/', $query->market)) {
                $tool['user_location'] = ['country' => strtoupper($query->market)];
            }
            $body['tools'] = [$tool];
        }

        return $http->post($this->baseUrl().'/v1/agent', $body);
    }

    protected function normalize(array $json): ?array
    {
        if (! isset($json['output']) || ! is_array($json['output'])) {
            return null;
        }

        $text = '';
        $citations = [];
        $resultSets = 0;

        foreach ($json['output'] as $item) {
            $type = $item['type'] ?? null;
            if ($type === 'search_results') {
                $resultSets++;
                foreach ($item['results'] ?? [] as $result) {
                    if (! empty($result['url'])) {
                        $citations[] = ['url' => $result['url'], 'title' => $result['title'] ?? null];
                    }
                }
            }
            if ($type !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $part) {
                if (($part['type'] ?? null) === 'output_text') {
                    $text .= (string) ($part['text'] ?? '');
                }
            }
        }

        $usage = $json['usage'] ?? [];
        $searches = null;
        foreach ($usage['tool_calls_details'] ?? [] as $tool => $details) {
            if (str_contains((string) $tool, 'search') && isset($details['invocation'])) {
                $searches = ($searches ?? 0) + (int) $details['invocation'];
            }
        }

        return [
            'text' => $text,
            'citations' => $this->uniqueCitations($citations),
            'input_tokens' => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null,
            'output_tokens' => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null,
            'search_calls' => $searches ?? $resultSets,
        ];
    }
}
