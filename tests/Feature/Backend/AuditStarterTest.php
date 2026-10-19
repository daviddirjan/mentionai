<?php

namespace Tests\Feature\Backend;

use App\Contracts\AuditStarter;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisTier;
use App\Exceptions\AnalysisRejected;
use App\Jobs\GenerateAuditReport;
use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Models\ApiUsageLog;
use App\Models\Lead;
use Database\Seeders\AiProviderSeeder;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditStarterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AiProviderSeeder::class, PromptTemplateSeeder::class]);
        config(['mentionai.ai_mode' => 'mock']);
        Queue::fake();
    }

    private function check(array $attributes = []): Analysis
    {
        return Analysis::factory()->completed()->create($attributes + [
            'location' => 'Oradea', 'industry' => 'Software Development', 'interface_locale' => 'ro', 'tier' => AnalysisTier::Check,
        ])->refresh();
    }

    private function lead(?Analysis $check = null): Lead
    {
        return Lead::factory()->create(['analysis_id' => $check?->id]);
    }

    private function reason(callable $fn): string
    {
        try {
            $fn();
        } catch (AnalysisRejected $e) {
            return $e->reason;
        }
        $this->fail('Expected AnalysisRejected');
    }

    public function test_starts_an_audit_from_a_completed_check_and_links_the_lead(): void
    {
        $check = $this->check();
        $lead = $this->lead($check);

        $result = app(AuditStarter::class)->start($check, $lead, '203.0.113.5');
        $audit = $result->analysis;

        $this->assertFalse($result->reused);
        $this->assertSame(AnalysisTier::Audit, $audit->tier);
        $this->assertSame($check->id, $audit->parent_analysis_id);
        $this->assertSame(AnalysisStatus::Pending, $audit->status);
        $this->assertTrue($audit->is_demo);
        foreach (['company_id', 'company_name', 'website_url', 'domain', 'market', 'location', 'language', 'industry', 'interface_locale'] as $field) {
            $this->assertSame($check->{$field}, $audit->{$field}, $field);
        }
        $this->assertNotSame($check->config_hash, $audit->config_hash);
        $this->assertSame($audit->id, $lead->refresh()->analysis_id);
        $this->assertTrue($check->audits()->whereKey($audit->id)->exists());
        Queue::assertPushed(RunAnalysis::class, fn (RunAnalysis $job) => $job->analysisId === $audit->id);
    }

    public function test_the_free_domain_limit_does_not_apply(): void
    {
        settings()->set('free_analyses_per_domain', 1);
        $check = $this->check();

        $this->assertFalse(app(AuditStarter::class)->start($check, $this->lead($check), '203.0.113.5')->reused);
    }

    public function test_only_completed_checks_are_eligible(): void
    {
        $pending = Analysis::factory()->create(['tier' => AnalysisTier::Check, 'status' => AnalysisStatus::QueryingProviders]);
        $failed = Analysis::factory()->failed()->create();
        $audit = Analysis::factory()->audit()->create();

        foreach ([$pending, $failed, $audit] as $analysis) {
            $this->assertSame(AnalysisRejected::NOT_ELIGIBLE, $this->reason(fn () => app(AuditStarter::class)->start($analysis->refresh(), $this->lead(), '203.0.113.5')));
        }
        Queue::assertNothingPushed();
    }

    public function test_disabled_audits_and_global_switch(): void
    {
        $check = $this->check();

        settings()->set('audit_enabled', false);
        $this->assertSame(AnalysisRejected::DISABLED, $this->reason(fn () => app(AuditStarter::class)->start($check, $this->lead(), '203.0.113.5')));

        settings()->set('audit_enabled', true);
        settings()->set('analyses_enabled', false);
        $this->assertSame(AnalysisRejected::DISABLED, $this->reason(fn () => app(AuditStarter::class)->start($check, $this->lead(), '203.0.113.5')));
        $this->assertSame(0, Analysis::query()->where('tier', 'audit')->count());
    }

    public function test_in_flight_and_completed_audits_are_reused_and_each_lead_is_linked(): void
    {
        $check = $this->check();
        $first = app(AuditStarter::class)->start($check, $this->lead($check), '203.0.113.5')->analysis;

        $secondLead = $this->lead($check);
        $second = app(AuditStarter::class)->start($check, $secondLead, '203.0.113.6');
        $this->assertTrue($second->reused);
        $this->assertSame($first->id, $second->analysis->id);
        $this->assertSame($first->id, $secondLead->refresh()->analysis_id);

        $first->update(['status' => AnalysisStatus::Completed, 'completed_at' => now()]);
        $third = app(AuditStarter::class)->start($check, $this->lead($check), '203.0.113.7');
        $this->assertTrue($third->reused);
        $this->assertSame($first->id, $third->analysis->id);
        Queue::assertPushed(GenerateAuditReport::class, fn (GenerateAuditReport $job) => $job->analysisId === $first->id && $job->notify);
        Queue::assertPushed(GenerateAuditReport::class, 1); // the in-flight reuse waits for the pipeline

        $first->update(['completed_at' => now()->subHours((int) settings('cache_hours') + 1)]);
        $this->assertFalse(app(AuditStarter::class)->start($check, $this->lead($check), '203.0.113.8')->reused);
        Queue::assertPushed(RunAnalysis::class, 2);
    }

    public function test_ip_daily_and_budget_limits_apply(): void
    {
        $check = $this->check();

        settings()->set('ip_analyses_per_hour', 1);
        app(AuditStarter::class)->start($check, $this->lead(), '203.0.113.5');
        Analysis::query()->where('tier', 'audit')->delete();
        $this->assertSame(AnalysisRejected::RATE_LIMITED, $this->reason(fn () => app(AuditStarter::class)->start($check, $this->lead(), '203.0.113.5')));

        settings()->set('max_analyses_per_day', 1);
        $this->assertSame(AnalysisRejected::DAILY_LIMIT, $this->reason(fn () => app(AuditStarter::class)->start($check, $this->lead(), '198.51.100.1')));

        settings()->set('max_analyses_per_day', 100);
        settings()->set('monthly_budget', 1.0);
        ApiUsageLog::create(['provider' => 'openai', 'operation' => 'query', 'status' => 'succeeded', 'estimated_cost' => 5, 'created_at' => now()]);
        $this->assertSame(AnalysisRejected::BUDGET_EXCEEDED, $this->reason(fn () => app(AuditStarter::class)->start($check, $this->lead(), '198.51.100.2')));
    }
}
