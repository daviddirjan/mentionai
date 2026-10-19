<?php

namespace Tests\Feature\Backend;

use App\Contracts\AnalysisStarter;
use App\Contracts\AuditStarter;
use App\Data\AnalysisInput;
use App\Enums\AnalysisStatus;
use App\Enums\ResponseStatus;
use App\Jobs\GenerateAuditReport;
use App\Models\AiProvider;
use App\Models\Analysis;
use App\Models\ApiUsageLog;
use App\Models\Lead;
use App\Services\Analysis\SpendingGuard;
use App\Services\Detection\TextNormalizer;
use App\Services\Inspection\SafeFetcher;
use App\Services\Inspection\WebsiteInspector;
use Database\Seeders\AiProviderSeeder;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AiProviderSeeder::class, PromptTemplateSeeder::class]);
        config(['mentionai.ai_mode' => 'mock', 'mentionai.turnstile.secret_key' => null]);
        $this->app->bind(WebsiteInspector::class, fn () => new WebsiteInspector(fn () => ['93.184.216.34']));
        $this->app->bind(SafeFetcher::class, fn () => new SafeFetcher(fn () => ['93.184.216.34']));

        Http::fake(function (Request $request) {
            return match (parse_url($request->url(), PHP_URL_PATH) ?: '/') {
                '/' => Http::response('<html><head><title>Acme</title></head><body><h1>Acme builds software</h1><h1>Two</h1></body></html>', 200, ['Content-Type' => 'text/html']),
                '/robots.txt' => Http::response("User-agent: GPTBot\nDisallow: /", 200, ['Content-Type' => 'text/plain']),
                default => Http::response('not found', 404, ['Content-Type' => 'text/plain']),
            };
        });
    }

    private function completedCheck(): Analysis
    {
        $check = app(AnalysisStarter::class)->start(new AnalysisInput('Acme Software SRL', 'https://www.acme-soft.ro', 'RO', 'ro', 'Software Development', 'ro', '203.0.113.9', null, 'Oradea'))->analysis->refresh();
        $this->assertSame(AnalysisStatus::Completed, $check->status);

        return $check;
    }

    public function test_free_check_is_unchanged_by_the_audit_tier(): void
    {
        Queue::fake([GenerateAuditReport::class]);

        $check = $this->completedCheck();

        $this->assertSame(3, $check->questions()->count());
        $this->assertSame(12, $check->responses()->count());
        $this->assertNull($check->site_audit);
        $this->assertNull($check->recommendations);
        $this->assertNull($check->narrative);
        Queue::assertNotPushed(GenerateAuditReport::class);
    }

    public function test_audit_runs_end_to_end_in_mock_mode(): void
    {
        Queue::fake([GenerateAuditReport::class]);
        $check = $this->completedCheck();
        $lead = Lead::factory()->create(['analysis_id' => $check->id, 'email' => 'owner@example.com', 'locale' => 'ro']);

        $result = app(AuditStarter::class)->start($check, $lead, '203.0.113.10');
        $audit = $result->analysis->refresh();

        $this->assertFalse($result->reused);
        $this->assertSame(AnalysisStatus::Completed, $audit->status);
        $this->assertTrue($audit->is_demo);
        $this->assertSame($audit->id, $lead->refresh()->analysis_id);

        $this->assertSame(25, $audit->questions()->count());
        $this->assertSame(100, $audit->responses()->count());
        $this->assertSame(100, $audit->successful_tests);
        $this->assertSame(100, ApiUsageLog::query()->where('analysis_id', $audit->id)->where('operation', 'query')->count());
        foreach ($audit->questions as $question) {
            $this->assertStringNotContainsString('acme', TextNormalizer::fold($question->question));
        }

        $checks = collect($audit->siteChecks())->keyBy('key');
        $this->assertCount(22, $checks);
        $this->assertSame('fail', $checks['ai_crawler_gptbot']->status->value);
        $this->assertSame('GET /sitemap.xml → 404', $checks['sitemap_xml']->evidence);
        $this->assertSame('2 × <h1>', $checks['h1_count']->evidence);

        $recs = collect($audit->recommendationList())->keyBy('key');
        $this->assertSame('GPTBot', $recs['allow_ai_crawlers']->params['bots']);
        $this->assertTrue($recs->has('publish_robots_sitemap'));
        $this->assertSame('third_party_profiles', $recs->keys()->last());

        $this->assertSame('demo', $audit->auditNarrative()->source);
        $this->assertGreaterThanOrEqual(3, count($audit->auditNarrative()->summary));

        Queue::assertPushed(GenerateAuditReport::class, fn (GenerateAuditReport $job) => $job->analysisId === $audit->id);
        Queue::assertPushed(GenerateAuditReport::class, 1);
    }

    public function test_provider_caps_are_raised_to_the_audit_question_count(): void
    {
        Queue::fake([GenerateAuditReport::class]);
        AiProvider::query()->update(['max_requests_per_analysis' => 3]);
        settings()->set('audit_query_count', 10);
        $check = $this->completedCheck();

        $audit = app(AuditStarter::class)->start($check, Lead::factory()->create(), '203.0.113.10')->analysis->refresh();

        $this->assertSame(10, $audit->questions()->count());
        $this->assertEquals(['openai' => 10, 'gemini' => 10, 'anthropic' => 10, 'perplexity' => 10], $audit->responses()->get()->groupBy('provider')->map->count()->all());
    }

    public function test_audit_with_no_successful_answer_fails_without_report(): void
    {
        Queue::fake([GenerateAuditReport::class]);
        $check = $this->completedCheck();
        AiProvider::query()->update(['is_enabled' => false]);
        AiProvider::query()->where('slug', 'openai')->update(['is_enabled' => true]);
        settings()->set('analyses_enabled', true);
        // A spending block skips every call: skipped calls are failures, never "not mentioned".
        settings()->set('max_cost_per_audit', 0);

        $audit = app(AuditStarter::class)->start($check, Lead::factory()->create(), '203.0.113.10')->analysis->refresh();

        $this->assertSame(AnalysisStatus::Failed, $audit->status);
        $this->assertSame(0, $audit->responses()->where('status', ResponseStatus::Succeeded->value)->count());
        $this->assertNull($audit->recommendations);
        Queue::assertNotPushed(GenerateAuditReport::class);
    }

    public function test_spending_guard_uses_the_audit_cap(): void
    {
        settings()->set('max_cost_per_analysis', 0.10);
        settings()->set('max_cost_per_audit', 2.00);
        $check = Analysis::factory()->create(['estimated_cost' => 0.5]);
        $audit = Analysis::factory()->create(['tier' => 'audit', 'estimated_cost' => 0.5]);

        $this->assertSame('budget_exceeded', app(SpendingGuard::class)->blockReason($check));
        $this->assertNull(app(SpendingGuard::class)->blockReason($audit->refresh()));
    }
}
