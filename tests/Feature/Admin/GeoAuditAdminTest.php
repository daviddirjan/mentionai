<?php

namespace Tests\Feature\Admin;

use App\Contracts\ReportRenderer;
use App\Enums\AnalysisTier;
use App\Enums\QuestionCategory;
use App\Filament\Pages\ApiCosts;
use App\Filament\Resources\Analyses\AnalysisResource;
use App\Filament\Resources\Analyses\Pages\ListAnalyses;
use App\Filament\Resources\Analyses\Pages\ViewAnalysis;
use App\Filament\Resources\PromptTemplates\Pages\CreatePromptTemplate;
use App\Filament\Resources\PromptTemplates\Pages\EditPromptTemplate;
use App\Filament\Resources\PromptTemplates\Pages\ListPromptTemplates;
use App\Filament\Widgets\StatsOverview;
use App\Models\AiProvider;
use App\Models\Analysis;
use App\Models\PromptTemplate;
use Database\Seeders\AiProviderSeeder;
use Database\Seeders\DemoDataSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;

class GeoAuditAdminTest extends AdminTestCase
{
    public function test_tier_column_and_filter(): void
    {
        $check = Analysis::factory()->completed()->create();
        $audit = Analysis::factory()->audit()->create(['parent_analysis_id' => $check->id]);

        Livewire::test(ListAnalyses::class)
            ->assertCanSeeTableRecords([$check, $audit])
            ->assertTableColumnFormattedStateSet('tier', 'GEO Audit', $audit)
            ->filterTable('tier', AnalysisTier::Audit->value)
            ->assertCanSeeTableRecords([$audit])
            ->assertCanNotSeeTableRecords([$check])
            ->filterTable('tier', AnalysisTier::Check->value)
            ->assertCanSeeTableRecords([$check])
            ->assertCanNotSeeTableRecords([$audit])
            ->resetTableFilters()
            ->callAction(TestAction::make('exportCsv')->table())
            ->assertFileDownloaded();
    }

    public function test_infolist_shows_checks_recommendations_and_narrative(): void
    {
        $check = Analysis::factory()->completed()->create();
        $audit = Analysis::factory()->audit()->create(['parent_analysis_id' => $check->id, 'report_generated_at' => now()]);

        $this->get(AnalysisResource::getUrl('view', ['record' => $audit]))
            ->assertOk()
            ->assertSee('Technical checks')
            ->assertSee('sitemap_xml')
            ->assertSee('GET /sitemap.xml → 404')
            ->assertSee('Recommendations')
            ->assertSee('publish_robots_sitemap')
            ->assertSee('P0')
            ->assertSee('visibility:local_not_recommended')
            ->assertSee('Narrative')
            ->assertSee('Demo verdict: the site is reachable')
            ->assertSee('Custom software development in Oradea')
            ->assertSee('DEMO')
            ->assertSee($check->uuid)
            ->assertSee(AnalysisResource::getUrl('view', ['record' => $check]))
            ->assertDontSee('&quot;site_audit&quot;', false);
    }

    public function test_audit_sections_are_hidden_for_checks(): void
    {
        $check = Analysis::factory()->completed()->create();

        $this->get(AnalysisResource::getUrl('view', ['record' => $check]))
            ->assertOk()
            ->assertDontSee('Technical checks')
            ->assertDontSee('Started from check');
    }

    public function test_download_pdf_streams_the_stored_report(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('reports/test.pdf', '%PDF-1.4 fake');
        $audit = Analysis::factory()->audit()->create([
            'domain' => 'axiontic.ro',
            'report_path' => 'reports/test.pdf',
            'report_generated_at' => now()->setDate(2026, 10, 1),
        ]);
        $check = Analysis::factory()->completed()->create();

        Livewire::test(ListAnalyses::class)
            ->assertActionHidden(TestAction::make('downloadReport')->table($check))
            ->callAction(TestAction::make('downloadReport')->table($audit))
            ->assertFileDownloaded('geo-audit-axiontic.ro-2026-10-01.pdf');

        Livewire::test(ViewAnalysis::class, ['record' => $audit->getRouteKey()])
            ->callAction('downloadReport')
            ->assertFileDownloaded('geo-audit-axiontic.ro-2026-10-01.pdf');
    }

    public function test_regenerate_calls_the_report_renderer(): void
    {
        $audit = Analysis::factory()->audit()->create();
        $check = Analysis::factory()->completed()->create();

        $renderer = new class implements ReportRenderer
        {
            public array $rendered = [];

            public function render(Analysis $analysis): string
            {
                $this->rendered[] = $analysis->uuid;
                $analysis->update(['report_path' => 'reports/x.pdf', 'report_generated_at' => now()]);

                return 'reports/x.pdf';
            }
        };
        $this->app->instance(ReportRenderer::class, $renderer);

        Livewire::test(ListAnalyses::class)
            ->assertActionHidden(TestAction::make('renderReport')->table($check))
            ->callAction(TestAction::make('renderReport')->table($audit))
            ->assertNotified('PDF report generated');

        $this->assertSame([$audit->uuid], $renderer->rendered);
        $this->assertTrue($audit->refresh()->hasReport());
    }

    public function test_regenerate_failure_shows_only_the_exception_class(): void
    {
        $audit = Analysis::factory()->audit()->create();
        $this->app->instance(ReportRenderer::class, new class implements ReportRenderer
        {
            public function render(Analysis $analysis): string
            {
                throw new RuntimeException('secret detail sk-123');
            }
        });

        Livewire::test(ViewAnalysis::class, ['record' => $audit->getRouteKey()])
            ->callAction('renderReport')
            ->assertNotified('PDF generation failed')
            ->assertDontSee('secret detail');
    }

    public function test_audit_settings_are_saved(): void
    {
        $this->seed(AiProviderSeeder::class);
        AiProvider::query()->update(['is_enabled' => true]);

        Livewire::test(ApiCosts::class)
            ->assertFormSet(['audit_enabled' => true, 'audit_query_count' => 25, 'report_link_days' => 30])
            ->fillForm([
                'audit_enabled' => false,
                'audit_query_count' => 40,
                'max_cost_per_audit' => 3.5,
                'audit_narrative_provider' => 'anthropic',
                'report_link_days' => 14,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(settings('audit_enabled'));
        $this->assertSame(40, settings('audit_query_count'));
        $this->assertSame(3.5, settings('max_cost_per_audit'));
        $this->assertSame('anthropic', settings('audit_narrative_provider'));
        $this->assertSame(14, settings('report_link_days'));
    }

    public function test_audit_settings_validation(): void
    {
        $this->seed(AiProviderSeeder::class);

        Livewire::test(ApiCosts::class)
            ->fillForm(['audit_query_count' => 51, 'report_link_days' => 91, 'max_cost_per_audit' => -1, 'audit_narrative_provider' => 'nope'])
            ->call('save')
            ->assertHasFormErrors(['audit_query_count', 'report_link_days', 'max_cost_per_audit', 'audit_narrative_provider']);
    }

    public function test_prompt_template_category_and_audit_only_are_editable(): void
    {
        Livewire::test(CreatePromptTemplate::class)
            ->fillForm(['name' => 'Local', 'template' => 'Best {service} in {city}?', 'sort_order' => 1, 'category' => 'local', 'is_audit_only' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = PromptTemplate::firstWhere('name', 'Local');
        $this->assertSame(QuestionCategory::Local, $template->category);
        $this->assertTrue($template->is_audit_only);

        Livewire::test(EditPromptTemplate::class, ['record' => $template->getKey()])
            ->fillForm(['category' => 'b2b', 'is_audit_only' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $template->refresh();
        $this->assertSame(QuestionCategory::B2b, $template->category);
        $this->assertFalse($template->is_audit_only);

        $other = PromptTemplate::create(['name' => 'Audit', 'template' => 'Compare {service}?', 'sort_order' => 2, 'category' => 'compare', 'is_audit_only' => true]);

        Livewire::test(ListPromptTemplates::class)
            ->filterTable('is_audit_only', true)
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$template])
            ->resetTableFilters()
            ->filterTable('category', 'b2b')
            ->assertCanSeeTableRecords([$template])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_stats_count_recent_audits_and_demo_seeder_adds_an_audit(): void
    {
        Analysis::factory()->audit()->create();
        Analysis::factory()->audit()->create(['created_at' => now()->subDays(40)]);

        Livewire::test(StatsOverview::class)->assertSee('GEO audits (30 days)');

        $this->seed(DemoDataSeeder::class);

        $audit = Analysis::where('tier', AnalysisTier::Audit->value)->where('is_demo', true)->first();
        $this->assertNotNull($audit);
        $this->assertNotNull($audit->parent_analysis_id);
        $this->assertTrue($audit->parent->is_demo);
        $this->assertSame(AnalysisTier::Check, $audit->parent->tier);
        $this->get(AnalysisResource::getUrl('view', ['record' => $audit]))->assertOk()->assertSee('Technical checks');
    }
}
