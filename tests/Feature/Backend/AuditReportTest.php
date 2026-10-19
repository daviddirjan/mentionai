<?php

namespace Tests\Feature\Backend;

use App\Contracts\AuditReportBuilder;
use App\Contracts\ReportRenderer;
use App\Data\AuditReport;
use App\Data\HistoryComparison;
use App\Enums\AnalysisStatus;
use App\Jobs\GenerateAuditReport;
use App\Models\Analysis;
use App\Models\Lead;
use App\Services\Audit\HistoryComparer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/** Stand-in for the Frontend mailable while it does not exist in this worktree. */
class StubAuditReadyMail extends Mailable
{
    public function __construct(public Analysis $audit, public string $reportUrl, public string $resultsUrl) {}

    public function build(): static
    {
        return $this->subject('Audit ready')->html('ready');
    }
}

class AuditReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The real view belongs to Frontend; only stand in for it when it is missing.
        if (! View::exists('pdf.geo-audit')) {
            $dir = storage_path('framework/testing/views');
            File::ensureDirectoryExists($dir.'/pdf');
            File::put($dir.'/pdf/geo-audit.blade.php', '<html><body><h1>{{ $report->analysis->domain }}</h1><p>{{ $report->narrative->verdict }}</p>@foreach ($report->questions as $q)<p>{{ $q[\'question\'] }}</p>@endforeach</body></html>');
            View::addLocation($dir);
        }
        if (! Route::has('report.download')) {
            Route::get('report/{analysis}.pdf', fn () => 'pdf')->name('report.download');
            Route::getRoutes()->refreshNameLookups();
        }
        if (! class_exists('App\\Mail\\AuditReadyMail')) {
            class_alias(StubAuditReadyMail::class, 'App\\Mail\\AuditReadyMail');
        }
    }

    public function test_report_builder_fills_every_field_for_a_factory_audit(): void
    {
        $audit = Analysis::factory()->audit()->create();

        $report = app(AuditReportBuilder::class)->build($audit);

        $this->assertInstanceOf(AuditReport::class, $report);
        $this->assertSame('demo', $report->narrative->source);
        $this->assertCount(8, $report->checks);
        $this->assertSame('publish_robots_sitemap', $report->recommendations[0]->key);

        $this->assertSame(['openai', 'gemini', 'anthropic', 'perplexity'], array_column($report->providers, 'slug'));
        $byslug = array_column($report->providers, null, 'slug');
        $this->assertSame('ChatGPT', $byslug['openai']['name']);
        $this->assertSame(['recommended' => 0, 'successful' => 2, 'failed' => 1], array_intersect_key($byslug['anthropic'], array_flip(['recommended', 'successful', 'failed'])));
        $this->assertEquals(66.67, $byslug['perplexity']['percentage']);
        $this->assertNotSame('', $byslug['gemini']['model']);

        $this->assertSame(['top'], array_keys($report->categories));
        $this->assertSame(['recommended' => 3, 'successful' => 11, 'percentage' => 27.27], $report->categories['top']);

        $this->assertCount(3, $report->questions);
        $first = $report->questions[0];
        $this->assertSame(1, $first['position']);
        $this->assertSame('top', $first['category']);
        $this->assertSame(['openai', 'gemini', 'anthropic', 'perplexity'], array_keys($first['results']));
        $this->assertSame('recommended', $first['results']['gemini']['classification']);
        $this->assertStringContainsString($audit->company_name, $first['results']['gemini']['snippet']);
        $this->assertSame('not_mentioned', $first['results']['openai']['classification']);
        $this->assertSame(['status' => 'failed', 'classification' => null, 'snippet' => null], $report->questions[2]['results']['anthropic']);
        foreach ($report->questions as $question) {
            foreach ($question['results'] as $result) {
                $this->assertTrue($result['snippet'] === null || mb_strlen($result['snippet']) <= 220);
                $this->assertStringNotContainsString('**', (string) $result['snippet']);
            }
        }

        $this->assertSame('Endava', $report->competitors[0]['name']);
        $this->assertSame(11, $report->competitors[0]['mentions']);
        $this->assertSame([['url' => 'https://'.$audit->domain, 'title' => $audit->company_name, 'count' => 3]], $report->sources);
        $this->assertNull($report->history);
        $this->assertSame([1, 2, 3], array_keys($report->plan()));
        $this->assertSame(['publish_robots_sitemap', 'fix_html_lang_h1', 'add_local_business_schema'], array_map(fn ($r) => $r->key, $report->plan()[1]));
    }

    public function test_report_builder_works_for_a_free_check(): void
    {
        $check = Analysis::factory()->completed()->create();

        $report = app(AuditReportBuilder::class)->build($check);

        $this->assertSame('rules', $report->narrative->source);
        $this->assertSame([], $report->checks);
        $recommendations = $report->recommendations;
        $this->assertSame('third_party_profiles', end($recommendations)->key);
        $this->assertCount(3, $report->questions);
    }

    public function test_history_compares_with_the_previous_matching_analysis(): void
    {
        $older = Analysis::factory()->audit()->create(['completed_at' => now()->subMonth()]);
        $older->update(['site_audit' => array_map(fn ($c) => $c['key'] === 'robots_txt' ? ['status' => 'pass'] + $c : ($c['key'] === 'homepage_status' ? ['status' => 'fail'] + $c : $c), $older->site_audit)]);
        $older->competitors()->where('name', 'Fortech')->delete();
        $older->competitors()->create(['name' => 'Gone Ltd', 'normalized_name' => 'gone', 'mention_count' => 2, 'providers' => ['openai']]);
        $older->update(['visibility_score' => 10, 'provider_scores' => ['openai' => ['recommended' => 1, 'successful' => 3, 'total' => 3, 'percentage' => 33.33]]]);

        // Different language or tier never counts as history.
        Analysis::factory()->audit()->create(['company_id' => $older->company_id, 'language' => 'en']);
        Analysis::factory()->completed()->create(['company_id' => $older->company_id, 'market' => 'RO', 'language' => 'ro']);

        $current = Analysis::factory()->audit()->create(['company_id' => $older->company_id]);

        $history = app(HistoryComparer::class)->compare($current);

        $this->assertInstanceOf(HistoryComparison::class, $history);
        $this->assertSame($older->uuid, $history->previousUuid);
        $this->assertEquals(10.0, $history->scoreBefore);
        $this->assertEquals(27.27, $history->scoreAfter);
        $this->assertEquals(['before' => 33.33, 'after' => 0.0], $history->providers['openai']);
        $this->assertEquals(['before' => null, 'after' => 66.67], $history->providers['perplexity']);
        $this->assertSame(['Fortech'], $history->competitorsGained);
        $this->assertSame(['Gone Ltd'], $history->competitorsLost);
        $this->assertSame(['homepage_status'], $history->checksFixed);
        $this->assertSame(['robots_txt'], $history->checksRegressed);

        $this->assertNull(app(HistoryComparer::class)->compare($older));
        $this->assertSame($older->uuid, app(AuditReportBuilder::class)->build($current)->history?->previousUuid);
    }

    public function test_history_ignores_other_locations_and_unfinished_analyses(): void
    {
        $older = Analysis::factory()->audit()->create(['location' => 'Cluj']);
        Analysis::factory()->create(['company_id' => $older->company_id, 'tier' => 'audit', 'location' => 'Oradea', 'status' => AnalysisStatus::Failed]);
        $current = Analysis::factory()->audit()->create(['company_id' => $older->company_id]);

        $this->assertNull(app(HistoryComparer::class)->compare($current));
    }

    public function test_renderer_writes_the_pdf_and_records_the_path(): void
    {
        Storage::fake('local');
        $audit = Analysis::factory()->audit()->create();

        $path = app(ReportRenderer::class)->render($audit);

        $this->assertSame("reports/{$audit->uuid}.pdf", $path);
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($path));
        $audit->refresh();
        $this->assertSame($path, $audit->report_path);
        $this->assertTrue($audit->hasReport());
        $this->assertDirectoryExists(storage_path('fonts'));
    }

    public function test_job_renders_and_mails_the_latest_lead_in_its_locale(): void
    {
        Storage::fake('local');
        Mail::fake();
        $audit = Analysis::factory()->audit()->create();
        Lead::factory()->create(['analysis_id' => $audit->id, 'email' => 'old@example.com', 'locale' => 'en']);
        Lead::factory()->create(['analysis_id' => $audit->id, 'email' => 'new@example.com', 'locale' => 'ro']);

        GenerateAuditReport::dispatchSync($audit->id);

        $this->assertTrue($audit->refresh()->hasReport());
        Mail::assertQueued('App\\Mail\\AuditReadyMail', function (Mailable $mail) use ($audit) {
            $this->assertSame('ro', $mail->locale);
            if ($mail instanceof StubAuditReadyMail) {
                $this->assertStringContainsString($audit->uuid, $mail->reportUrl);
                $this->assertStringContainsString('signature=', $mail->reportUrl);
                $this->assertStringContainsString('/ro/analysis/'.$audit->uuid, $mail->resultsUrl);
            }

            return $mail->hasTo('new@example.com');
        });
        Mail::assertQueuedCount(1);

        // A regenerated report (admin retry) does not email again; a new lead on a reused audit does get one.
        GenerateAuditReport::dispatchSync($audit->id);
        Mail::assertQueuedCount(1);
        GenerateAuditReport::dispatchSync($audit->id, notify: true);
        Mail::assertQueuedCount(2);
    }

    public function test_job_without_lead_only_renders(): void
    {
        Storage::fake('local');
        Mail::fake();
        $audit = Analysis::factory()->audit()->create();

        GenerateAuditReport::dispatchSync($audit->id);

        $this->assertTrue($audit->refresh()->hasReport());
        Mail::assertNothingQueued();
    }

    public function test_job_ignores_checks_and_unfinished_audits(): void
    {
        Storage::fake('local');
        Mail::fake();
        $check = Analysis::factory()->completed()->create();
        $pending = Analysis::factory()->create(['tier' => 'audit']);

        GenerateAuditReport::dispatchSync($check->id);
        GenerateAuditReport::dispatchSync($pending->id);

        $this->assertFalse($check->refresh()->hasReport());
        $this->assertFalse($pending->refresh()->hasReport());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
