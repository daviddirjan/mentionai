<?php

namespace Tests\Feature\Backend;

use App\Contracts\ProviderRegistry;
use App\Data\ProviderQuery;
use App\Enums\ProviderSlug;
use App\Models\AiProvider;
use App\Models\ApiUsageLog;
use App\Services\Ai\Adapters\AnthropicAdapter;
use App\Services\Ai\Adapters\GeminiAdapter;
use App\Services\Ai\Adapters\MockAdapter;
use App\Services\Ai\Adapters\OpenAiAdapter;
use App\Services\Ai\Adapters\PerplexityAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdapterTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk-test-SECRET-1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (ProviderSlug::cases() as $slug) {
            config(["mentionai.providers.{$slug->value}.api_key" => self::SECRET]);
        }
    }

    private function provider(ProviderSlug $slug): AiProvider
    {
        return AiProvider::factory()->slug($slug)->create(['model' => 'm-1', 'max_search_calls' => 2, 'max_output_tokens' => 500, 'timeout_seconds' => 20]);
    }

    private function adapter(ProviderSlug $slug): object
    {
        return match ($slug) {
            ProviderSlug::OpenAI => new OpenAiAdapter,
            ProviderSlug::Gemini => new GeminiAdapter,
            ProviderSlug::Anthropic => new AnthropicAdapter,
            ProviderSlug::Perplexity => new PerplexityAdapter,
        };
    }

    private function pq(): ProviderQuery
    {
        return new ProviderQuery('Recommend the best accountants in Romania.', 'en', 'RO');
    }

    /** @return array<string, array{0: ProviderSlug, 1: string, 2: array}> */
    public static function successPayloads(): array
    {
        return [
            'openai' => [ProviderSlug::OpenAI, 'api.openai.com/v1/responses', [
                'output' => [
                    ['type' => 'web_search_call', 'status' => 'completed'],
                    ['type' => 'message', 'content' => [[
                        'type' => 'output_text', 'text' => '1. **Alpha Co**',
                        'annotations' => [['type' => 'url_citation', 'url' => 'https://alpha.example', 'title' => 'Alpha'], ['type' => 'url_citation', 'url' => 'https://alpha.example', 'title' => 'dup']],
                    ]]],
                ],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ], 1],
            'gemini' => [ProviderSlug::Gemini, 'generativelanguage.googleapis.com/v1beta/models/m-1:generateContent', [
                'candidates' => [['content' => ['parts' => [['text' => '1. **Alpha '], ['text' => 'Co**']]], 'groundingMetadata' => [
                    'groundingChunks' => [['web' => ['uri' => 'https://alpha.example', 'title' => 'alpha.example']], ['other' => []]],
                    'webSearchQueries' => ['q1', 'q2'],
                ]]],
                'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 50],
            ], 2],
            'anthropic' => [ProviderSlug::Anthropic, 'api.anthropic.com/v1/messages', [
                'content' => [
                    ['type' => 'server_tool_use', 'name' => 'web_search'],
                    ['type' => 'web_search_tool_result', 'content' => [['type' => 'web_search_result', 'url' => 'https://alpha.example', 'title' => 'Alpha']]],
                    ['type' => 'text', 'text' => '1. **Alpha ', 'citations' => [['type' => 'web_search_result_location', 'url' => 'https://alpha.example', 'title' => 'Alpha']]],
                    ['type' => 'text', 'text' => 'Co**'],
                ],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50, 'server_tool_use' => ['web_search_requests' => 1]],
            ], 1],
            'perplexity' => [ProviderSlug::Perplexity, 'api.perplexity.ai/chat/completions', [
                'choices' => [['message' => ['content' => '1. **Alpha Co**']]],
                'citations' => ['https://alpha.example'],
                'search_results' => [['title' => 'Alpha', 'url' => 'https://alpha.example']],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ], 1],
        ];
    }

    #[DataProvider('successPayloads')]
    public function test_success_is_normalized(ProviderSlug $slug, string $endpoint, array $payload, int $searches): void
    {
        Http::fake(["{$endpoint}" => Http::response($payload)]);
        $provider = $this->provider($slug);

        $result = $this->adapter($slug)->query($provider, $this->pq());

        $this->assertTrue($result->successful);
        $this->assertSame($slug->value, $result->provider);
        $this->assertSame('m-1', $result->model);
        $this->assertStringContainsString('Alpha', $result->text);
        $this->assertSame([['url' => 'https://alpha.example', 'title' => $result->citations[0]['title']]], $result->citations);
        $this->assertSame(100, $result->inputTokens);
        $this->assertSame(50, $result->outputTokens);
        $this->assertSame($searches, $result->searchCalls);
        $this->assertFalse($result->isDemo);
        $this->assertIsArray($result->raw);
    }

    public function test_request_shapes_and_auth(): void
    {
        Http::fake(fn () => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]]]));
        $this->adapter(ProviderSlug::OpenAI)->query($this->provider(ProviderSlug::OpenAI), $this->pq());
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.self::SECRET)
            && $r['model'] === 'm-1' && $r['tools'][0]['type'] === 'web_search' && $r['max_output_tokens'] === 500 && $r['max_tool_calls'] === 2);

        Http::fake(fn () => Http::response(['content' => [['type' => 'text', 'text' => 'ok']]]));
        $this->adapter(ProviderSlug::Anthropic)->query($this->provider(ProviderSlug::Anthropic), $this->pq());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/messages') && $r->hasHeader('x-api-key', self::SECRET)
            && $r->hasHeader('anthropic-version', '2023-06-01') && $r['tools'][0] === ['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 2]);

        Http::fake(fn () => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]]));
        $this->adapter(ProviderSlug::Gemini)->query($this->provider(ProviderSlug::Gemini), $this->pq());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), ':generateContent') && ! str_contains($r->url(), self::SECRET)
            && $r->hasHeader('x-goog-api-key', self::SECRET) && isset($r['tools'][0]['google_search']));
    }

    #[DataProvider('successPayloads')]
    public function test_failures_are_mapped_and_never_leak_the_key(ProviderSlug $slug, string $endpoint): void
    {
        $provider = $this->provider($slug);
        $adapter = $this->adapter($slug);

        $cases = [
            [fn () => Http::response(['error' => ['message' => 'bad key '.self::SECRET]], 401), 'auth_error'],
            [fn () => Http::response(['error' => ['message' => 'forbidden']], 403), 'auth_error'],
            [fn () => Http::response(['error' => ['message' => 'slow down']], 429), 'rate_limited'],
            [fn () => Http::response('boom', 503), 'provider_error'],
            [fn () => throw new ConnectionException('cURL error 28: Operation timed out after 20000 milliseconds'), 'timeout'],
            [fn () => throw new ConnectionException('cURL error 6: Could not resolve host'), 'connection_error'],
            [fn () => Http::response(['unexpected' => true]), 'invalid_response'],
            [fn () => Http::response('not json at all'), 'invalid_response'],
            [fn () => Http::response(['choices' => [['message' => ['content' => '']]], 'content' => [], 'candidates' => [], 'output' => []]), 'invalid_response'],
        ];

        $current = null;
        Http::fake(['*' => function () use (&$current) {
            return $current();
        }]);

        foreach ($cases as [$fake, $code]) {
            $current = $fake;
            $result = $adapter->query($provider, $this->pq());

            $this->assertFalse($result->successful, $code);
            $this->assertSame($code, $result->errorCode);
            $this->assertStringNotContainsString(self::SECRET, (string) $result->errorMessage);
        }
    }

    public function test_missing_api_key_returns_failure_without_http(): void
    {
        Http::fake();
        config(['mentionai.providers.openai.api_key' => null]);

        $result = $this->adapter(ProviderSlug::OpenAI)->query($this->provider(ProviderSlug::OpenAI), $this->pq());

        $this->assertFalse($result->successful);
        $this->assertSame('missing_credentials', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_test_connection_updates_provider_and_logs_usage(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'OK']]], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1]])]);
        $provider = $this->provider(ProviderSlug::Perplexity);

        $result = $this->adapter(ProviderSlug::Perplexity)->testConnection($provider);

        $this->assertTrue($result->ok);
        $this->assertSame('ok', $provider->refresh()->last_test_status);
        $this->assertNotNull($provider->last_tested_at);
        $this->assertSame(1, ApiUsageLog::where('operation', 'test_connection')->where('status', 'succeeded')->count());
    }

    public function test_test_connection_failure_is_recorded_without_search_tool(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'nope']], 401)]);
        $provider = $this->provider(ProviderSlug::OpenAI);

        $result = $this->adapter(ProviderSlug::OpenAI)->testConnection($provider);

        $this->assertFalse($result->ok);
        $this->assertSame('failed', $provider->refresh()->last_test_status);
        Http::assertSent(fn (Request $r) => ! isset($r['tools']));
        $this->assertSame('auth_error', ApiUsageLog::where('operation', 'test_connection')->value('error_code'));
    }

    public function test_mock_adapter_is_deterministic_demo_and_offline(): void
    {
        Http::fake();
        $provider = $this->provider(ProviderSlug::Gemini);
        $adapter = new MockAdapter;

        $a = $adapter->query($provider, $this->pq());
        $b = $adapter->query($provider, $this->pq());

        $this->assertEquals($a, $b);
        $this->assertTrue($a->isDemo);
        $this->assertTrue($a->successful);
        $this->assertSame(0.0, $provider->estimateCost($a->inputTokens, $a->outputTokens, $a->searchCalls));
        Http::assertNothingSent();
    }

    public function test_mock_adapter_exercises_all_brand_variants(): void
    {
        $provider = $this->provider(ProviderSlug::OpenAI);
        $adapter = (new MockAdapter)->forBrand('Zorbix', 'zorbix.example');
        $withBrand = 0;
        $without = 0;

        for ($i = 0; $i < 60; $i++) {
            $text = $adapter->query($provider, new ProviderQuery("Question number {$i}?", 'en', 'RO'))->text;
            str_contains($text, 'Zorbix') ? $withBrand++ : $without++;
        }

        $this->assertGreaterThan(5, $withBrand);
        $this->assertGreaterThan(5, $without);
    }

    public function test_registry_uses_mock_in_mock_mode_and_real_adapters_in_live_mode(): void
    {
        $registry = app(ProviderRegistry::class);

        config(['mentionai.ai_mode' => 'mock']);
        $this->assertTrue($registry->isMockMode());
        foreach (ProviderSlug::cases() as $slug) {
            $this->assertInstanceOf(MockAdapter::class, $registry->adapterFor($this->provider($slug)));
        }

        config(['mentionai.ai_mode' => 'live']);
        $this->assertFalse($registry->isMockMode());
        $this->assertInstanceOf(OpenAiAdapter::class, $registry->adapterFor(AiProvider::where('slug', 'openai')->first()));
        $this->assertInstanceOf(GeminiAdapter::class, $registry->adapterFor(AiProvider::where('slug', 'gemini')->first()));
        $this->assertInstanceOf(AnthropicAdapter::class, $registry->adapterFor(AiProvider::where('slug', 'anthropic')->first()));
        $this->assertInstanceOf(PerplexityAdapter::class, $registry->adapterFor(AiProvider::where('slug', 'perplexity')->first()));
    }

    public function test_live_mode_without_key_fails_instead_of_falling_back_to_mock(): void
    {
        Http::fake();
        config(['mentionai.ai_mode' => 'live', 'mentionai.providers.anthropic.api_key' => '']);
        $provider = $this->provider(ProviderSlug::Anthropic);

        $result = app(ProviderRegistry::class)->adapterFor($provider)->query($provider, $this->pq());

        $this->assertFalse($result->successful);
        $this->assertFalse($result->isDemo);
        $this->assertSame('missing_credentials', $result->errorCode);
    }
}
