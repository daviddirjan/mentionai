<?php

namespace App\Services\Ai\Adapters;

use App\Data\ProviderQuery;
use App\Models\AiProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/** OpenAI Responses API with the built-in web_search tool. */
class OpenAiAdapter extends HttpProviderAdapter
{
    protected function slug(): string
    {
        return 'openai';
    }

    protected function authenticate(PendingRequest $http, string $apiKey): PendingRequest
    {
        return $http->withToken($apiKey);
    }

    protected function send(PendingRequest $http, AiProvider $provider, ProviderQuery $query, bool $withSearch, int $maxTokens): Response
    {
        $body = [
            'model' => $provider->model,
            'input' => $query->question,
            'max_output_tokens' => $maxTokens,
        ];

        if ($withSearch) {
            $tool = ['type' => 'web_search'];
            if (preg_match('/^[A-Za-z]{2}$/', $query->market)) {
                $tool['user_location'] = ['type' => 'approximate', 'country' => strtoupper($query->market)];
            }
            $body['tools'] = [$tool];
            if ($provider->max_search_calls) {
                $body['max_tool_calls'] = (int) $provider->max_search_calls;
            }
        }

        return $http->post($this->baseUrl().'/responses', $body);
    }

    protected function normalize(array $json): ?array
    {
        if (! isset($json['output']) || ! is_array($json['output'])) {
            return null;
        }

        $text = '';
        $citations = [];
        $searches = 0;

        foreach ($json['output'] as $item) {
            $type = $item['type'] ?? null;
            if ($type === 'web_search_call') {
                $searches++;
            }
            if ($type !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $part) {
                if (($part['type'] ?? null) !== 'output_text') {
                    continue;
                }
                $text .= (string) ($part['text'] ?? '');
                foreach ($part['annotations'] ?? [] as $annotation) {
                    if (($annotation['type'] ?? null) === 'url_citation' && ! empty($annotation['url'])) {
                        $citations[] = ['url' => $annotation['url'], 'title' => $annotation['title'] ?? null];
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
            'search_calls' => $searches,
        ];
    }
}
