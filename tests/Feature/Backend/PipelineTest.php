<?php

namespace Tests\Feature\Backend;

use App\Contracts\AnalysisMaintenance;
use App\Contracts\AnalysisStarter;
use App\Data\AnalysisInput;
use App\Enums\AnalysisStatus;
use App\Enums\ResponseStatus;
use App\Jobs\QueryProvider;
use App\Models\AiProvider;
use App\Models\Analysis;
use App\Models\ApiUsageLog;
use App\Services\Inspection\WebsiteInspector;
use Database\Seeders\AiProviderSeeder;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AiProviderSeeder::class, PromptTemplateSeeder::class]);
        config(['mentionai.ai_mode' => 'mock', 'mentionai.turnstile.secret_key' => null]);
        $this->app->bind(WebsiteInspector::class, fn () => new WebsiteInspector(fn () => ['93.184.216.34']));
        // The homepage fetch must not leave the test process.
        Http::fake(['*' => Http::response('<html><head><title>Acme</title></head><body><h1>Acme builds software</h1></body></html>', 200, ['Content-Type' => 'text/html'])]);
    }

    private function input(string $name = 'Acme Software SRL', string $url = 'https://www.acme-soft.ro'): AnalysisInput
    {
        return new AnalysisInput($name, $url, 'RO', 'ro', 'Software Development', 'en', '203.0.113.9');
    }

    public function test_full_pipeline_runs_in_mock_mode(): void
    {
        $result = app(AnalysisStarter::class)->start($this->input());
        $analysis = $result->analysis->refresh();

        $this->assertFalse($result->reused);
        $this->assertSame(AnalysisStatus::Completed, $analysis->status);
        $this->assertTrue($analysis->is_demo);
        $this->assertSame(3, $analysis->questions()->count());
        $this->assertSame(12, $analysis->responses()->count());
        $this->assertSame(12, $analysis->successful_tests);
        $this->assertSame(0, $analysis->failed_tests);
        $this->assertNotNull($analysis->visibility_score);
        $this->assertSame(12, $analysis->brandMentions()->count());
        $this->assertGreaterThan(0, $analysis->competitors()->count());
        $this->assertSame(12, ApiUsageLog::where('analysis_id', $analysis->id)->where('operation', 'query')->count());
        $this->assertEquals(0, $analysis->estimated_cost);
        $this->assertTrue($analysis->website_inspection['fetched'] ?? false);

        foreach ($analysis->questions as $question) {
            $this->assertStringNotContainsStringIgnoringCase('acme', $question->question);
        }
        foreach ($analysis->responses as $response) {
            $this->assertTrue($response->is_demo);
        }
        $this->assertSame(
            $analysis->recommendation_count,
            $analysis->brandMentions()->where('classification', 'recommended')->count(),
        );
    }

    public function test_every_response_gets_a_row_per_question_and_provider(): void
    {
        $analysis = app(AnalysisStarter::class)->start($this->input())->analysis;
        $perProvider = $analysis->responses()->get()->groupBy('provider')->map->count();

        $this->assertEquals(['openai' => 3, 'gemini' => 3, 'anthropic' => 3, 'perplexity' => 3], $perProvider->all());
    }

    public function test_retry_only_requeues_failed_responses(): void
    {
        $analysis = app(AnalysisStarter::class)->start($this->input())->analysis;
        $failed = $analysis->responses()->where('provider', 'gemini')->first();
        $failed->update(['status' => ResponseStatus::Failed, 'error_code' => 'timeout', 'response_text' => null]);
        $untouched = $analysis->responses()->where('provider', 'openai')->first();
        $untouchedAttempts = $untouched->attempts;
        $logsBefore = ApiUsageLog::count();

        $count = app(AnalysisMaintenance::class)->retryFailed($analysis);
        $analysis->refresh();

        $this->assertSame(1, $count);
        $this->assertSame(ResponseStatus::Succeeded, $failed->refresh()->status);
        $this->assertSame(2, $failed->attempts);
        $this->assertSame($untouchedAttempts, $untouched->refresh()->attempts);
        $this->assertSame($logsBefore + 1, ApiUsageLog::count());
        $this->assertSame(AnalysisStatus::Completed, $analysis->status);
        $this->assertSame(12, $analysis->successful_tests);
        $this->assertSame(0, app(AnalysisMaintenance::class)->retryFailed($analysis));
    }

    public function test_query_provider_is_idempotent_for_succeeded_rows(): void
    {
        $analysis = app(AnalysisStarter::class)->start($this->input())->analysis;
        $response = $analysis->responses()->first();
        $logs = ApiUsageLog::count();

        QueryProvider::dispatchSync($response->id);

        $this->assertSame($logs, ApiUsageLog::count());
        $this->assertSame(1, $response->refresh()->attempts);
    }

    public function test_budget_exceeded_skips_calls_and_fails_analysis_without_blaming_the_brand(): void
    {
        settings()->set('monthly_budget', 1.0);
        ApiUsageLog::create([
            'provider' => 'openai', 'operation' => 'query', 'status' => 'succeeded', 'estimated_cost' => 5, 'created_at' => now(),
        ]);
        // Start directly (the starter itself would reject on budget) to exercise the job-level guard.
        $company = \App\Models\Company::factory()->create(['domain' => 'budget.example']);
        $analysis = Analysis::factory()->create(['company_id' => $company->id, 'language' => 'en']);
        \App\Jobs\RunAnalysis::dispatchSync($analysis->id);
        $analysis->refresh();

        $this->assertSame(AnalysisStatus::Failed, $analysis->status);
        $this->assertSame(0, $analysis->successful_tests);
        $this->assertSame(12, $analysis->responses()->where('status', ResponseStatus::Skipped->value)->where('error_code', 'budget_exceeded')->count());
        $this->assertNull($analysis->visibility_score);
        $this->assertSame(0, $analysis->brandMentions()->count());
    }

    public function test_recalculate_rebuilds_from_stored_responses_without_provider_calls(): void
    {
        $analysis = app(AnalysisStarter::class)->start($this->input())->analysis;
        $analysis->brandMentions()->delete();
        $analysis->update(['visibility_score' => null, 'successful_tests' => 0]);
        $logs = ApiUsageLog::count();

        $fresh = app(AnalysisMaintenance::class)->recalculate($analysis);

        $this->assertSame(12, $fresh->successful_tests);
        $this->assertSame(12, $fresh->brandMentions()->count());
        $this->assertSame($logs, ApiUsageLog::count());
        $this->assertGreaterThan(0, AiProvider::count());
    }
}
