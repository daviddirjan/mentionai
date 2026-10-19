<?php

namespace App\Mail;

use App\Enums\ProviderSlug;
use App\Models\Analysis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent by the backend when the audit PDF is ready, in the lead's interface locale
 * (the caller sets ->locale()). $reportUrl is a temporary signed report.download URL.
 */
class AuditReadyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Analysis $audit,
        public string $reportUrl,
        public string $resultsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('site.mail.audit_ready_subject', ['domain' => $this->audit->domain]));
    }

    public function content(): Content
    {
        $tests = (int) $this->audit->successful_tests + (int) $this->audit->failed_tests;

        return new Content(view: 'mail.audit-ready', with: [
            'domain' => $this->audit->domain,
            'tests' => $tests > 0 ? $tests : (int) settings('audit_query_count', 25) * count(ProviderSlug::cases()),
            'isDemo' => (bool) $this->audit->is_demo,
            'reportUrl' => $this->reportUrl,
            'resultsUrl' => $this->resultsUrl,
            'days' => (int) settings('report_link_days', 30),
        ]);
    }
}
