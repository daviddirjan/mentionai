<?php

namespace Tests\Feature\Backend;

use App\Contracts\AuditNarrator;
use App\Data\AuditNarrative;
use App\Models\AiProvider;
use App\Models\Analysis;
use App\Models\ApiUsageLog;
use App\Models\Lead;
use Database\Seeders\AiProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuditNarratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AiProviderSeeder::class);
        AiProvider::query()->update(['input_price_per_million' => 1, 'output_price_per_million' => 4]);
    }

    private function live(): void
    {
        config(['mentionai.ai_mode' => 'live', 'mentionai.providers.openai.api_key' => 'sk-test-secret']);
    }

    private function openAiReply(string $text): array
    {
        return [
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]]]],
            'usage' => ['input_tokens' => 900, 'output_tokens' => 300],
        ];
    }

    private function audit(): Analysis
    {
        $audit = Analysis::factory()->audit()->create(['narrative' => null]);
        Lead::factory()->create(['analysis_id' => $audit->id, 'email' => 'owner@secret-lead.example']);

        return $audit->refresh();
    }

    private function assertValidShape(AuditNarrative $narrative): void
    {
        $this->assertNotSame('', $narrative->verdict);
        $this->assertLessThanOrEqual(400, mb_strlen($narrative->verdict));
        $this->assertGreaterThanOrEqual(3, count($narrative->summary));
        $this->assertLessThanOrEqual(5, count($narrative->summary));
        foreach ($narrative->summary as $item) {
            $this->assertIsString($item);
            $this->assertLessThanOrEqual(220, mb_strlen($item));
        }
        $this->assertTrue($narrative->h1 === null || mb_strlen($narrative->h1) <= 70);
        $this->assertTrue($narrative->title === null || mb_strlen($narrative->title) <= 65);
        $this->assertTrue($narrative->metaDescription === null || mb_strlen($narrative->metaDescription) <= 160);
    }

    public function test_mock_mode_returns_a_deterministic_demo_narrative_without_calls(): void
    {
        config(['mentionai.ai_mode' => 'mock']);
        Http::fake();
        $audit = $this->audit();

        $first = app(AuditNarrator::class)->narrate($audit);
        $second = app(AuditNarrator::class)->narrate($audit);

        $this->assertSame('demo', $first->source);
        $this->assertEquals($first, $second);
        $this->assertValidShape($first);
        $prefix = __('audit.narrative.demo_prefix', [], $audit->language);
        $this->assertStringStartsWith($prefix, $first->verdict);
        foreach ($first->summary as $item) {
            $this->assertStringStartsWith($prefix, $item);
        }
        Http::assertNothingSent();
        $this->assertSame(0, ApiUsageLog::query()->where('operation', 'narrative')->count());
    }

    public function test_live_valid_json_becomes_an_ai_narrative_and_is_logged(): void
    {
        $this->live();
        $json = json_encode([
            'verdict' => 'Site-ul este vizibil parțial.',
            'summary' => ['Unu.', 'Doi.', 'Trei.', 'Patru.'],
            'h1' => 'Dezvoltare software în Oradea',
            'title' => 'Dezvoltare software Oradea',
            'meta_description' => 'Aplicații web pentru firme din Oradea.',
        ]);
        Http::fake(['api.openai.com/*' => Http::response($this->openAiReply("```json\n{$json}\n```"))]);
        $audit = $this->audit();

        $narrative = app(AuditNarrator::class)->narrate($audit);

        $this->assertSame('ai', $narrative->source);
        $this->assertSame('Site-ul este vizibil parțial.', $narrative->verdict);
        $this->assertSame(['Unu.', 'Doi.', 'Trei.', 'Patru.'], $narrative->summary);
        $this->assertSame('Dezvoltare software în Oradea', $narrative->h1);
        $log = ApiUsageLog::query()->where('operation', 'narrative')->sole();
        $this->assertSame('succeeded', $log->status);
        $this->assertSame($audit->id, $log->analysis_id);
        $this->assertSame('openai', $log->provider);
        $this->assertFalse($log->is_demo);
        $this->assertGreaterThan(0, (float) $log->estimated_cost);
    }

    public function test_prompt_carries_aggregates_only(): void
    {
        $this->live();
        Http::fake(['api.openai.com/*' => Http::response($this->openAiReply('{}'))]);
        $audit = $this->audit();

        app(AuditNarrator::class)->narrate($audit);

        Http::assertSent(function (Request $request) use ($audit) {
            $body = $request->body();
            $this->assertStringNotContainsString('owner@secret-lead.example', $body);
            $this->assertStringNotContainsString('sk-test-secret', $body);
            $this->assertStringNotContainsString('strong custom software team', $body); // raw answer text
            $this->assertStringNotContainsString('outsourcing', $body);
            $this->assertStringContainsString($audit->domain, $body);
            $this->assertStringContainsString('robots_txt', $body);
            $this->assertStringContainsString('do not invent numbers, clients, years, addresses', $body);

            return true;
        });
    }

    public function test_garbage_output_falls_back_to_rules(): void
    {
        $this->live();
        Http::fake(['api.openai.com/*' => Http::response($this->openAiReply('Sure! Here is your summary: the site is great.'))]);

        $narrative = app(AuditNarrator::class)->narrate($this->audit());

        $this->assertSame('rules', $narrative->source);
        $this->assertValidShape($narrative);
        $this->assertSame('failed', ApiUsageLog::query()->where('operation', 'narrative')->sole()->status);
    }

    public function test_too_few_summary_items_fall_back_to_rules(): void
    {
        $this->live();
        Http::fake(['api.openai.com/*' => Http::response($this->openAiReply(json_encode(['verdict' => 'Ok.', 'summary' => ['One.']])))]);

        $this->assertSame('rules', app(AuditNarrator::class)->narrate($this->audit())->source);
    }

    public function test_long_values_are_clamped(): void
    {
        $this->live();
        Http::fake(['api.openai.com/*' => Http::response($this->openAiReply(json_encode([
            'verdict' => str_repeat('a', 900),
            'summary' => array_fill(0, 7, str_repeat('b', 400)),
            'h1' => str_repeat('c', 200),
            'title' => str_repeat('d', 200),
            'meta_description' => str_repeat('e', 400),
        ])))]);

        $narrative = app(AuditNarrator::class)->narrate($this->audit());

        $this->assertSame('ai', $narrative->source);
        $this->assertValidShape($narrative);
        $this->assertCount(5, $narrative->summary);
        $this->assertSame(400, mb_strlen($narrative->verdict));
    }

    public function test_provider_error_falls_back_to_rules_and_never_throws(): void
    {
        $this->live();
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $narrative = app(AuditNarrator::class)->narrate($this->audit());

        $this->assertSame('rules', $narrative->source);
        $this->assertSame('failed', ApiUsageLog::query()->where('operation', 'narrative')->sole()->status);
    }

    public function test_disabled_or_unknown_narrative_provider_makes_no_call(): void
    {
        $this->live();
        Http::fake();
        settings()->set('audit_narrative_provider', 'nope');
        $this->assertSame('rules', app(AuditNarrator::class)->narrate($this->audit())->source);

        settings()->set('audit_narrative_provider', 'openai');
        AiProvider::query()->where('slug', 'openai')->update(['is_enabled' => false]);
        $this->assertSame('rules', app(AuditNarrator::class)->narrate($this->audit())->source);

        Http::assertNothingSent();
    }

    public function test_configured_provider_is_used(): void
    {
        config(['mentionai.ai_mode' => 'live', 'mentionai.providers.anthropic.api_key' => 'sk-ant-test']);
        settings()->set('audit_narrative_provider', 'anthropic');
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['verdict' => 'V.', 'summary' => ['a', 'b', 'c']])]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ])]);

        $narrative = app(AuditNarrator::class)->narrate($this->audit());

        $this->assertSame('ai', $narrative->source);
        $this->assertSame('anthropic', ApiUsageLog::query()->where('operation', 'narrative')->sole()->provider);
    }

    public function test_rules_narrative_uses_lang_keys_in_the_analysis_language(): void
    {
        config(['mentionai.ai_mode' => 'live']); // no key → adapter fails → rules
        $audit = $this->audit();

        $narrative = app(AuditNarrator::class)->narrate($audit);

        $this->assertSame('rules', $narrative->source);
        $this->assertValidShape($narrative);
        // Score 27.27 is in the "mid" band.
        $this->assertSame(__('audit.narrative.verdict_mid', ['domain' => $audit->domain, 'score' => '27.3', 'recommended' => 3, 'successful' => 11], 'ro'), $narrative->verdict);
        $this->assertNotNull($narrative->h1);
    }
}
