<?php

namespace App\Jobs;

use App\Contracts\ReportRenderer;
use App\Enums\AnalysisStatus;
use App\Models\Analysis;
use App\Models\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/** Renders the GEO audit PDF, then mails the audit's latest lead a signed download link (first report only). */
class GenerateAuditReport implements ShouldQueue
{
    use Queueable;

    public const MAILABLE = 'App\\Mail\\AuditReadyMail';

    public int $tries = 2;

    public int $timeout = 180;

    /** @param  bool  $notify  mail the latest lead even when a report already existed (a new lead reused a finished audit) */
    public function __construct(public int $analysisId, public bool $notify = false) {}

    public function handle(ReportRenderer $renderer): void
    {
        $audit = Analysis::query()->find($this->analysisId);
        if ($audit === null || ! $audit->isAudit() || $audit->status !== AnalysisStatus::Completed) {
            return;
        }

        // An admin retry re-renders the PDF with fresh data but must not email the visitor a second time.
        $firstReport = $this->notify || ! $audit->hasReport();
        $renderer->render($audit);

        $lead = $audit->leads()->whereNotNull('email')->latest('id')->first();
        if (! $firstReport || $lead === null || blank($lead->email)) {
            return;
        }

        $this->mail($audit->refresh(), $lead);
    }

    private function mail(Analysis $audit, Lead $lead): void
    {
        $mailable = self::MAILABLE;
        if (! class_exists($mailable) || ! Route::has('report.download') || ! Route::has('analysis.show')) {
            Log::warning('Audit report mail skipped: mailable or routes are not available.', ['analysis' => $audit->uuid]);

            return;
        }

        $locale = $lead->locale ?: $audit->interface_locale;
        if (! in_array($locale, config('mentionai.locales'), true)) {
            $locale = settings('default_locale') ?: 'en';
        }

        $reportUrl = URL::temporarySignedRoute('report.download', now()->addDays((int) settings('report_link_days')), ['analysis' => $audit->uuid]);
        $resultsUrl = route('analysis.show', ['locale' => $locale, 'analysis' => $audit->uuid]);

        Mail::to($lead->email)->locale($locale)->queue(new $mailable($audit, $reportUrl, $resultsUrl));
    }
}
