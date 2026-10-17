<?php

namespace App\Services\Ai\Adapters;

use App\Data\ProviderQuery;
use App\Models\AiProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/** Anthropic Messages API with the server-side web search tool. */
class AnthropicAdapter extends HttpProviderAdapter
{
    protected function slug(): string
    {
        return 'anthropic';
    }

    protected function authenticate(PendingRequest $http, string $apiKey): PendingRequest
    {
        return $http->withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01']);
    }

    protected function send(PendingRequest $http, AiProvider $provider, ProviderQuery $query, bool $withSearch, int $maxTokens): Response
    {
        $body = [
            'model' => $provider->model,
            'max_tokens' => $maxTokens,
            'messages' => [['role' => 'user', 'content' => $query->question]],
        ];

        if ($withSearch) {
            $tool = ['type' => 'web_search_20250305', 'name' => 'web_search'];
            if ($provider->max_search_calls) {
                $tool['max_uses'] = (int) $provider->max_search_calls;
            }
            $body['tools'] = [$tool];
        }

        return $http->post($this->baseUrl().'/messages', $body);
    }

    protected function normalize(array $json): ?array
    {
        if (! isset($json['content']) || ! is_array($json['content'])) {
            return null;
        }

        $text = '';
        $citations = [];

        foreach ($json['content'] as $block) {
            $type = $block['type'] ?? null;

            if ($type === 'text') {
                $text .= (string) ($block['text'] ?? '');
                foreach ($block['citations'] ?? [] as $citation) {
                    if (! empty($citation['url'])) {
                        $citations[] = ['url' => $citation['url'], 'title' => $citation['title'] ?? null];
                    }
                }
            } elseif ($type === 'web_search_tool_result' && is_array($block['content'] ?? null)) {
                foreach ($block['content'] as $result) {
                    if (($result['type'] ?? null) === 'web_search_result' && ! empty($result['url'])) {
                        $citations[] = ['url' => $result['url'], 'title' => $result['title'] ?? null];
                    }
                }
            }
        }

        $usage = $json['usage'] ?? [];

        return [
            'text' => $text,
            'citations' => $this->uniqueCitations($citations),
            'input_tokens' => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null,
            'output_tokens' => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null,
            'search_calls' => isset($usage['server_tool_use']['web_search_requests']) ? (int) $usage['server_tool_use']['web_search_requests'] : null,
        ];
    }
}
