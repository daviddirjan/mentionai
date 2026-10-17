<?php

namespace Tests\Feature\Backend;

use App\Contracts\AnalysisStarter;
use App\Data\AnalysisInput;
use App\Enums\AnalysisStatus;
use App\Exceptions\AnalysisRejected;
use App\Jobs\RunAnalysis;
use App\Models\AiProvider;
use App\Models\Analysis;
use App\Models\ApiUsageLog;
use App\Models\Company;
use App\Models\PromptTemplate;
use Database\Seeders\AiProviderSeeder;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalysisStarterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AiProviderSeeder::class, PromptTemplateSeeder::class]);
        config(['mentionai.ai_mode' => 'mock', 'mentionai.turnstile.secret_key' => null]);
        Queue::fake();
        Cache::flush();
    }

    private function input(array $o = []): AnalysisInput
    {
        return new AnalysisInput(
            $o['name'] ?? 'Acme Software',
            $o['url'] ?? 'https://www.acme-soft.ro/about',
            $o['market'] ?? 'RO',
            $o['language'] ?? 'ro',
            'Software',
            'en',
            $o['ip'] ?? '203.0.113.5',
            $o['token'] ?? null,
        );
    }

    private function reason(callable $fn): AnalysisRejected
    {
        try {
            $fn();
        } catch (AnalysisRejected $e) {
            return $e;
        }
        $this->fail('Expected AnalysisRejected');
    }

    private function start(array $o = [])
    {
        return app(AnalysisStarter::class)->start($this->input($o));
    }

    public function test_creates_company_and_analysis_and_dispatches_pipeline(): void
    {
        $result = $this->start();
        $a = $result->analysis;

        $this->assertFalse($result->reused);
        $this->assertSame('acme-soft.ro', $a->domain);
        $this->assertSame('https://www.acme-soft.ro/about', $a->website_url);
        $this->assertSame(AnalysisStatus::Pending, $a->status);
        $this->assertTrue($a->is_demo);
        $this->assertSame(hash('sha256', '203.0.113.5'.config('app.key')), $a->ip_hash);
        $this->assertSame(64, strlen($a->config_hash));
        $this->assertSame('Acme Software', Company::where('domain', 'acme-soft.ro')->value('name'));
        Queue::assertPushed(RunAnalysis::class, fn ($job) => $job->analysisId === $a->id);
    }

    public function test_company_is_reused_by_domain_and_name_is_updated(): void
    {
        $this->start();
        Analysis::query()->update(['status' => AnalysisStatus::Failed->value]); // failed runs do not consume the free quota
        $this->start(['name' => 'Acme Software SRL', 'language' => 'en']);

        $this->assertSame(1, Company::count());
        $this->assertSame('Acme Software SRL', Company::first()->name);
    }

    public function test_invalid_urls_are_rejected(): void
    {
        foreach (['', 'not a url', 'http://127.0.0.1', 'javascript:alert(1)', 'localhost', 'ftp://acme.ro'] as $url) {
            $this->assertSame(AnalysisRejected::INVALID_URL, $this->reason(fn () => $this->start(['url' => $url]))->reason, $url);
        }
        Queue::assertNothingPushed();
        $this->assertSame(0, Analysis::count());
    }

    public function test_turnstile_is_enforced_only_when_configured(): void
    {
        $this->start(['url' => 'free.example']); // no secret: skipped

        config(['mentionai.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);

        $this->assertSame(AnalysisRejected::CAPTCHA_FAILED, $this->reason(fn () => $this->start(['token' => 'bad']))->reason);
        $this->assertSame(AnalysisRejected::CAPTCHA_FAILED, $this->reason(fn () => $this->start(['token' => null]))->reason);
        $this->assertFalse($this->start(['token' => 'good', 'url' => 'captcha.example'])->reused);
    }

    public function test_global_switch_and_no_enabled_providers_disable_analyses(): void
    {
        settings()->set('analyses_enabled', false);
        $this->assertSame(AnalysisRejected::DISABLED, $this->reason(fn () => $this->start())->reason);

        settings()->set('analyses_enabled', true);
        AiProvider::query()->update(['is_enabled' => false]);
        $this->assertSame(AnalysisRejected::DISABLED, $this->reason(fn () => $this->start())->reason);
        Queue::assertNothingPushed();
    }

    public function test_ip_rate_limit(): void
    {
        settings()->set('ip_analyses_per_hour', 2);
        settings()->set('free_analyses_per_domain', 99);

        $this->start(['url' => 'a1.example']);
        $this->start(['url' => 'a2.example']);

        $this->assertSame(AnalysisRejected::RATE_LIMITED, $this->reason(fn () => $this->start(['url' => 'a3.example']))->reason);
        $this->start(['url' => 'a3.example', 'ip' => '198.51.100.7']); // another IP is unaffected
        $this->assertSame(3, Analysis::count());
    }

    public function test_cache_reuse_of_a_recent_completed_analysis(): void
    {
        $first = $this->start()->analysis;
        $first->update(['status' => AnalysisStatus::Completed, 'completed_at' => now()->subHours(2)]);

        $again = $this->start(['ip' => '198.51.100.9']);

        $this->assertTrue($again->reused);
        $this->assertSame($first->id, $again->analysis->id);
        $this->assertSame(1, Analysis::count());
        Queue::assertPushed(RunAnalysis::class, 1);
    }

    public function test_expired_cache_and_window_allow_a_fresh_analysis(): void
    {
        $first = $this->start()->analysis;
        $first->forceFill(['status' => AnalysisStatus::Completed, 'completed_at' => now()->subHours(25), 'created_at' => now()->subHours(25)])->save();

        $second = $this->start();

        $this->assertFalse($second->reused);
        $this->assertNotSame($first->id, $second->analysis->id);
    }

    public function test_in_flight_duplicate_is_reused_not_duplicated(): void
    {
        $first = $this->start()->analysis;
        $second = $this->start(['ip' => '198.51.100.9']);

        $this->assertTrue($second->reused);
        $this->assertSame($first->id, $second->analysis->id);
        Queue::assertPushed(RunAnalysis::class, 1);
    }

    public function test_stuck_in_flight_analysis_does_not_block_forever(): void
    {
        $first = $this->start()->analysis;
        $first->forceFill(['created_at' => now()->subHours(30)])->save();

        $second = $this->start();

        $this->assertFalse($second->reused);
    }

    public function test_config_hash_changes_with_language_market_templates_and_mode(): void
    {
        $base = $this->start()->analysis->config_hash;
        Analysis::query()->update(['status' => AnalysisStatus::Failed->value]);
        $lang = $this->start(['language' => 'en'])->analysis->config_hash;
        Analysis::query()->update(['status' => AnalysisStatus::Failed->value]);
        $market = $this->start(['market' => 'DE'])->analysis->config_hash;
        Analysis::query()->update(['status' => AnalysisStatus::Failed->value]);
        PromptTemplate::query()->where('is_enabled', false)->first()->update(['is_enabled' => true]);
        $templates = $this->start()->analysis->config_hash;
        Analysis::query()->update(['status' => AnalysisStatus::Failed->value]);
        config(['mentionai.ai_mode' => 'live']);
        $mode = $this->start()->analysis->config_hash;

        $this->assertCount(5, array_unique([$base, $lang, $market, $templates, $mode]));
    }

    public function test_per_domain_limit_returns_existing_uuid_and_is_configurable(): void
    {
        $first = $this->start()->analysis;

        $e = $this->reason(fn () => $this->start(['language' => 'en']));
        $this->assertSame(AnalysisRejected::DOMAIN_LIMIT, $e->reason);
        $this->assertSame($first->uuid, $e->existingUuid);

        settings()->set('free_analyses_per_domain', 2);
        $this->assertFalse($this->start(['language' => 'en'])->reused);
    }

    public function test_failed_analyses_do_not_consume_the_domain_quota(): void
    {
        $this->start()->analysis->update(['status' => AnalysisStatus::Failed]);

        $this->assertFalse($this->start()->reused);
    }

    public function test_daily_limit(): void
    {
        settings()->set('max_analyses_per_day', 1);
        $this->start(['url' => 'd1.example']);

        $this->assertSame(AnalysisRejected::DAILY_LIMIT, $this->reason(fn () => $this->start(['url' => 'd2.example']))->reason);
    }

    public function test_monthly_budget_blocks_new_analyses_but_not_cache_hits(): void
    {
        $cached = $this->start()->analysis;
        $cached->update(['status' => AnalysisStatus::Completed, 'completed_at' => now()]);
        settings()->set('monthly_budget', 1.0);
        ApiUsageLog::create(['provider' => 'openai', 'operation' => 'query', 'status' => 'succeeded', 'estimated_cost' => 2, 'created_at' => now()]);

        $this->assertTrue($this->start()->reused);
        $this->assertSame(AnalysisRejected::BUDGET_EXCEEDED, $this->reason(fn () => $this->start(['url' => 'b1.example']))->reason);
    }
}
