<?php

namespace Tests\Feature\Frontend;

use App\Contracts\AuditReportBuilder;
use App\Enums\AnalysisStatus;
use App\Enums\AnalysisTier;
use App\Enums\CheckGroup;
use App\Enums\CheckStatus;
use App\Enums\Priority;
use App\Enums\QuestionCategory;
use App\Exceptions\AnalysisRejected;
use App\Livewire\AnalysisResults;
use App\Livewire\LeadForm;
use App\Mail\AuditReadyMail;
use App\Mail\NewLeadNotification;
use App\Models\Analysis;
use App\Models\Lead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

class GeoAuditFrontendTest extends FrontendTestCase
{
    private const CHECK_KEYS = [
        'homepage_status', 'https', 'robots_txt', 'sitemap_xml', 'llms_txt', 'response_time',
        'ai_crawler_gptbot', 'ai_crawler_oai_searchbot', 'ai_crawler_claudebot', 'ai_crawler_perplexitybot', 'ai_crawler_google_extended',
        'title_tag', 'meta_description', 'h1_count', 'html_lang', 'canonical', 'robots_meta', 'hreflang', 'open_graph',
        'jsonld_present', 'jsonld_local', 'jsonld_locality_match',
    ];

    private const REC_KEYS = [
        'fix_homepage_status', 'fix_https', 'publish_robots_sitemap', 'allow_ai_crawlers', 'fix_robots_meta', 'fix_html_lang_h1',
        'fix_title_meta', 'add_canonical', 'add_local_business_schema', 'add_hreflang', 'add_open_graph', 'publish_llms_txt',
        'local_landing_page', 'competitor_gap', 'provider_gap', 'case_studies', 'earn_citations', 'third_party_profiles',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
        RateLimiter::clear('lead:127.0.0.1');
    }

    private function url(Analysis $analysis, string $locale = 'en'): string
    {
        return "/{$locale}/analysis/{$analysis->uuid}";
    }

    private function auditWithReport(array $attributes = []): Analysis
    {
        $audit = Analysis::factory()->audit()->create($attributes);
        Storage::disk('local')->put("reports/{$audit->uuid}.pdf", '%PDF-1.7 test');
        $audit->update(['report_path' => "reports/{$audit->uuid}.pdf", 'report_generated_at' => now()]);

        return $audit;
    }

    // Landing

    public function test_comparison_table_is_rendered_in_every_locale(): void
    {
        foreach (config('mentionai.locales') as $locale) {
            $response = $this->get("/{$locale}")->assertOk();
            $response->assertSee(__('site.compare.title', [], $locale));
            foreach (trans('site.compare.rows', [], $locale) as $row) {
                $response->assertSee($row['feature'])->assertSee($row['chatgpt'])->assertSee($row['mentionai']);
            }
        }

        $this->get('/ro')->assertSee('ChatGPT obișnuit')->assertSee('MentionAI GEO Audit')->assertSee('Necesită verificări manuale')->assertSee('Istoric și comparații automate');
    }

    public function test_pricing_shows_free_check_and_audit_without_a_price(): void
    {
        $this->get('/en')
            ->assertSee('Free check')
            ->assertSee('GEO Audit')
            ->assertSee('100')
            ->assertSee('After email')
            ->assertDontSee('RON');
    }

    // Free check → audit offer

    public function test_completed_check_shows_the_audit_offer_with_email_form(): void
    {
        $check = Analysis::factory()->completed()->create();

        $this->get($this->url($check))
            ->assertSee('data-testid="audit-offer"', false)
            ->assertSee('Get the full GEO Audit')
            ->assertSee('100 AI tests')
            ->assertSee('Start my GEO Audit')
            ->assertSee('AI providers');
    }

    public function test_audit_offer_is_hidden_when_audits_are_disabled(): void
    {
        settings()->set('audit_enabled', false);
        $check = Analysis::factory()->completed()->create();

        $this->get($this->url($check))->assertDontSee('data-testid="audit-offer"', false);
    }

    public function test_audit_email_creates_lead_starts_audit_grants_access_and_redirects(): void
    {
        $check = Analysis::factory()->completed()->create();

        $component = Livewire::test(LeadForm::class, ['analysisUuid' => $check->uuid, 'mode' => 'audit'])
            ->set('email', 'ana@example.com')
            ->call('submit')
            ->assertHasNoErrors();

        $audit = $this->auditStarter->audit;
        $this->assertNotNull($audit);
        $component->assertRedirect(route('analysis.show', ['locale' => 'en', 'analysis' => $audit->uuid]));

        $lead = Lead::firstOrFail();
        $this->assertSame('ana@example.com', $lead->email);
        $this->assertSame('en', $lead->locale);
        $this->assertSame($check->id, $this->auditStarter->received['check']->id);
        $this->assertSame($lead->id, $this->auditStarter->received['lead']->id);
        $this->assertSame('127.0.0.1', $this->auditStarter->received['ip']);
        $this->assertTrue(session("audit_access.{$audit->uuid}"));
        Mail::assertQueued(NewLeadNotification::class);
    }

    public function test_audit_email_is_validated(): void
    {
        $check = Analysis::factory()->completed()->create();

        Livewire::test(LeadForm::class, ['analysisUuid' => $check->uuid, 'mode' => 'audit'])
            ->set('email', 'not-an-email')
            ->call('submit')
            ->assertHasErrors(['email' => 'email']);

        $this->assertNull($this->auditStarter->received);
        $this->assertSame(0, Lead::count());
    }

    public function test_audit_rejection_is_shown(): void
    {
        $check = Analysis::factory()->completed()->create();
        $this->auditStarter->reject = new AnalysisRejected(AnalysisRejected::BUDGET_EXCEEDED);

        Livewire::test(LeadForm::class, ['analysisUuid' => $check->uuid, 'mode' => 'audit'])
            ->set('email', 'ana@example.com')
            ->call('submit')
            ->assertSee(__('analysis.rejected.budget_exceeded'))
            ->assertNoRedirect();
    }

    // Audit page

    public function test_running_audit_uses_audit_progress_steps(): void
    {
        $audit = Analysis::factory()->create(['tier' => AnalysisTier::Audit, 'status' => AnalysisStatus::AuditingSite]);

        $this->get($this->url($audit))
            ->assertSee('GEO Audit in progress')
            ->assertSee(__('analysis.status.auditing_site'))
            ->assertSee('wire:poll', false);

        $check = Analysis::factory()->create(['status' => AnalysisStatus::QueryingProviders]);
        $this->get($this->url($check))->assertDontSee(__('analysis.status.auditing_site'));
    }

    public function test_completed_audit_renders_verdict_findings_priorities_and_badge(): void
    {
        $audit = $this->auditWithReport();

        $this->get($this->url($audit))
            ->assertOk()
            ->assertSee('data-testid="audit-badge"', false)
            ->assertSee('Demo verdict: the site is reachable')
            ->assertSee('Technical findings')
            ->assertSee('GET /sitemap.xml → 404')
            ->assertSee(__('audit.check.sitemap_xml.impact'))
            ->assertSee('P0')
            ->assertSee(__('audit.rec.local_landing_page.action', ['location' => 'Oradea']))
            ->assertSee('Visibility by question type')
            ->assertDontSee('data-testid="audit-offer"', false)
            ->assertDontSee(':location')
            ->assertDontSee(':domain');
    }

    public function test_pdf_button_needs_session_access(): void
    {
        $audit = $this->auditWithReport();

        $this->get($this->url($audit))
            ->assertSee('data-testid="pdf-unlock"', false)
            ->assertDontSee('data-testid="pdf-link"', false);

        $this->withSession(["audit_access.{$audit->uuid}" => true])
            ->get($this->url($audit))
            ->assertSee('data-testid="pdf-link"', false)
            ->assertSee(route('report.download', ['analysis' => $audit->uuid]), false);
    }

    public function test_unlock_form_sets_session_access(): void
    {
        $audit = $this->auditWithReport();

        Livewire::test(LeadForm::class, ['analysisUuid' => $audit->uuid, 'mode' => 'unlock'])
            ->set('email', 'bob@example.com')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('analysis.show', ['locale' => 'en', 'analysis' => $audit->uuid]));

        $this->assertTrue(session("audit_access.{$audit->uuid}"));
        $this->assertNull($this->auditStarter->received);
        $this->assertSame($audit->id, Lead::firstOrFail()->analysis_id);
    }

    public function test_pdf_being_generated_polls(): void
    {
        $audit = Analysis::factory()->audit()->create();

        Livewire::withQueryParams([])->test(AnalysisResults::class, ['uuid' => $audit->uuid])
            ->assertSee('data-testid="pdf-generating"', false)
            ->assertSee('wire:poll.5s', false);
    }

    public function test_demo_audit_is_labelled(): void
    {
        $audit = $this->auditWithReport(['is_demo' => true]);

        $this->get($this->url($audit))->assertSee('DEMO —', false);
    }

    // Report download

    public function test_report_download_requires_signature_or_session(): void
    {
        $audit = $this->auditWithReport(['domain' => 'axiontic.com']);
        $plain = route('report.download', ['analysis' => $audit->uuid]);

        $this->get($plain)->assertForbidden();

        $signed = URL::temporarySignedRoute('report.download', now()->addDays(30), ['analysis' => $audit->uuid]);
        $response = $this->get($signed)->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=geo-audit-axiontic.com-'.$audit->report_generated_at->format('Y-m-d').'.pdf', $response->headers->get('Content-Disposition'));

        $expired = URL::temporarySignedRoute('report.download', now()->subMinute(), ['analysis' => $audit->uuid]);
        $this->get($expired)->assertForbidden();

        $this->withSession(["audit_access.{$audit->uuid}" => true])->get($plain)->assertOk();
    }

    public function test_report_download_is_404_without_a_report_or_for_unknown_ids(): void
    {
        $audit = Analysis::factory()->audit()->create();

        $this->withSession(["audit_access.{$audit->uuid}" => true])
            ->get(route('report.download', ['analysis' => $audit->uuid]))
            ->assertNotFound();

        $this->get('/report/'.fake()->uuid().'.pdf')->assertNotFound();
        $this->get('/report/1.pdf')->assertNotFound();
        $this->assertStringStartsWith(url('/report/'), route('report.download', ['analysis' => $audit->uuid]));
    }

    // Mail

    public function test_audit_ready_mail_renders_localized_links(): void
    {
        $audit = Analysis::factory()->audit()->create(['domain' => 'axiontic.com']);
        $mail = (new AuditReadyMail($audit, 'https://example.test/report.pdf?signature=abc', 'https://example.test/ro/analysis/'.$audit->uuid))->locale('ro');

        $mail->assertSeeInHtml('axiontic.com');
        $mail->assertSeeInHtml('https://example.test/report.pdf?signature=abc', false);
        $mail->assertSeeInHtml(__('site.mail.audit_download', [], 'ro'));
        $mail->assertHasSubject(__('site.mail.audit_ready_subject', ['domain' => 'axiontic.com'], 'ro'));
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $mail);
    }

    // PDF template

    public function test_pdf_view_renders_every_section_in_the_analysis_language(): void
    {
        $audit = Analysis::factory()->audit()->create(['language' => 'ro', 'interface_locale' => 'en', 'domain' => 'firma-test.ro', 'industry' => 'Clinică stomatologică']);
        $audit->update(['site_audit' => array_merge($audit->site_audit, [
            ['key' => 'ai_crawler_claudebot', 'group' => 'ai_crawlers', 'status' => 'fail', 'evidence' => 'Disallow: / <script>x</script>', 'params' => ['bot' => 'ClaudeBot']],
        ])]);
        $report = app(AuditReportBuilder::class)->build($audit->refresh());

        $html = view('pdf.geo-audit', ['report' => $report])->render();

        foreach (['summary', 'methodology', 'visibility', 'questions', 'competitors', 'findings', 'priorities', 'plan', 'history', 'kpis', 'sources'] as $section) {
            $this->assertStringContainsString(e(__("audit.pdf.section.{$section}", [], 'ro')), $html, $section);
        }
        $this->assertStringContainsString('firma-test.ro', $html);
        $this->assertStringContainsString('Piața România, cu accent pe Oradea', $html);
        $this->assertStringContainsString('Clinică stomatologică', $html);
        $this->assertStringContainsString('Ce nu s-a măsurat', $html);
        $this->assertStringContainsString('Search Console', $html);
        $this->assertStringContainsString('Recommend software development companies in Romania (#1).', $html);
        $this->assertStringContainsString(e(__('analysis.classification.recommended', [], 'ro')), $html);
        $this->assertStringContainsString('Endava', $html);
        $this->assertStringContainsString(e('GET /sitemap.xml → 404'), $html);
        $this->assertStringContainsString('Acces ClaudeBot', $html);
        $this->assertStringContainsString('counter(page)', $html);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
        $this->assertStringNotContainsString(':location', $html);
        $this->assertStringNotContainsString('audit.pdf.', $html);
        $this->assertStringNotContainsString('DEMO —', $html);
    }

    public function test_pdf_view_shows_demo_label(): void
    {
        $audit = Analysis::factory()->demo()->audit()->create(['language' => 'en']);

        $html = view('pdf.geo-audit', ['report' => app(AuditReportBuilder::class)->build($audit)])->render();

        $this->assertStringContainsString('DEMO — simulated data', $html);
    }

    public function test_dompdf_renders_the_audit_as_a_pdf(): void
    {
        $audit = Analysis::factory()->audit()->create(['language' => 'ro']);
        File::ensureDirectoryExists(storage_path('fonts'));

        $output = Pdf::setOption(['isRemoteEnabled' => false, 'chroot' => base_path(), 'fontDir' => storage_path('fonts'), 'fontCache' => storage_path('fonts')])
            ->loadView('pdf.geo-audit', ['report' => app(AuditReportBuilder::class)->build($audit)])
            ->setPaper('a4')
            ->output();

        $this->assertStringStartsWith('%PDF', $output);
        $this->assertGreaterThan(10_000, strlen($output));
    }

    // Translations

    public function test_every_check_recommendation_and_enum_has_audit_translations(): void
    {
        foreach (config('mentionai.locales') as $locale) {
            foreach (self::CHECK_KEYS as $key) {
                foreach (['title', 'impact'] as $field) {
                    $this->assertNotSame("audit.check.{$key}.{$field}", __("audit.check.{$key}.{$field}", [], $locale), "{$locale} {$key}.{$field}");
                }
            }
            foreach (self::REC_KEYS as $key) {
                foreach (['action', 'acceptance', 'why'] as $field) {
                    $this->assertNotSame("audit.rec.{$key}.{$field}", __("audit.rec.{$key}.{$field}", [], $locale), "{$locale} {$key}.{$field}");
                }
            }
            foreach ([...QuestionCategory::cases(), ...CheckGroup::cases()] as $case) {
                $this->assertNotSame($case->translationKey(), __($case->translationKey(), [], $locale));
            }
            foreach (CheckStatus::cases() as $case) {
                $this->assertNotSame("audit.status.{$case->value}", __("audit.status.{$case->value}", [], $locale));
            }
            foreach (Priority::cases() as $case) {
                $this->assertNotSame("audit.priority.{$case->value}", __("audit.priority.{$case->value}", [], $locale));
            }
        }
    }
}
