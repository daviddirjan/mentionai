<?php

namespace Tests\Feature\Admin;

use App\Enums\LeadStatus;
use App\Filament\Resources\Analyses\AnalysisResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\AnalysesPerDayChart;
use App\Filament\Widgets\ApiCostPerDayChart;
use App\Filament\Widgets\RecentAnalyses;
use App\Filament\Widgets\RecentLeads;
use App\Filament\Widgets\ResponsesByProviderChart;
use App\Filament\Widgets\StatsOverview;
use App\Models\Analysis;
use App\Models\ApiUsageLog;
use App\Models\Lead;
use Database\Seeders\DemoDataSeeder;
use Livewire\Livewire;

class DashboardTest extends AdminTestCase
{
    public function test_dashboard_and_widgets_render_on_empty_database(): void
    {
        $this->get('/admin')->assertOk();

        foreach ([StatsOverview::class, AnalysesPerDayChart::class, ApiCostPerDayChart::class, ResponsesByProviderChart::class, RecentAnalyses::class, RecentLeads::class] as $widget) {
            Livewire::test($widget)->assertOk();
        }

        Livewire::test(StatsOverview::class)->assertSee('Available after payments launch')->assertSee('n/a');
    }

    public function test_stats_count_data_and_exclude_demo_costs(): void
    {
        $live = Analysis::factory()->completed()->create();
        $live->update(['visibility_score' => 40]);
        Analysis::factory()->completed()->demo()->create()->update(['visibility_score' => 60]);
        Lead::factory()->create(['status' => LeadStatus::New]);
        $this->usage($live, false, 1.25);
        $this->usage($live, true, 7.5);

        Livewire::test(StatsOverview::class)
            ->assertSee('Total analyses')
            ->assertSee('1 demo')
            ->assertSee('50.0%')
            ->assertSee('$1.25')
            ->assertDontSee('$8.75')
            ->assertSee('Estimated');
    }

    public function test_charts_and_recent_tables_show_data(): void
    {
        $analysis = Analysis::factory()->completed()->demo()->create();
        $lead = Lead::factory()->create();

        Livewire::test(RecentAnalyses::class)->assertCanSeeTableRecords([$analysis])->assertSee('DEMO');
        Livewire::test(RecentLeads::class)->assertCanSeeTableRecords([$lead]);
        Livewire::test(ResponsesByProviderChart::class)->assertOk();
    }

    public function test_demo_seeder_populates_the_admin_panel(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertGreaterThanOrEqual(8, Analysis::count());
        $this->assertSame(0, Analysis::where('is_demo', false)->count());
        $this->assertSame(6, collect(LeadStatus::cases())->filter(fn ($s) => Lead::where('status', $s)->exists())->count());
        $this->assertGreaterThan(0, ApiUsageLog::where('is_demo', true)->count());
        $this->assertSame(0, ApiUsageLog::where('is_demo', false)->count());
        $this->assertDatabaseHas('analyses', ['status' => 'failed']);
        $this->assertDatabaseHas('analyses', ['status' => 'querying_providers']);

        foreach ([AnalysisResource::class, LeadResource::class, ContactMessageResource::class, UserResource::class] as $resource) {
            $this->get($resource::getUrl('index'))->assertOk();
        }
        $this->get(AnalysisResource::getUrl('view', ['record' => Analysis::where('status', 'completed')->first()]))->assertOk();
        $this->get('/admin')->assertOk();
        Livewire::test(StatsOverview::class)->assertOk();
        Livewire::test(ApiCostPerDayChart::class)->assertOk();
    }

    public function test_all_admin_pages_load(): void
    {
        $this->seed(DemoDataSeeder::class);

        foreach (['/admin', '/admin/analyses', '/admin/leads', '/admin/contact-messages', '/admin/ai-providers', '/admin/prompt-templates', '/admin/users', '/admin/api-costs', '/admin/website-settings'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    private function usage(Analysis $analysis, bool $demo, float $cost): void
    {
        ApiUsageLog::create([
            'analysis_id' => $analysis->id, 'provider' => 'openai', 'operation' => 'query', 'status' => 'succeeded',
            'is_demo' => $demo, 'estimated_cost' => $cost, 'created_at' => now(),
        ]);
    }
}
