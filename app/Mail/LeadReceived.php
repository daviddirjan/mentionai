<?php

namespace App\Mail;

use App\Models\Analysis;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Confirmation sent to the visitor after a lead request, in their interface language. */
class LeadReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead)
    {
        $this->locale($lead->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('site.mail.lead_received_subject'));
    }

    public function content(): Content
    {
        $analysis = $this->lead->analysis_id ? Analysis::find($this->lead->analysis_id) : null;

        return new Content(view: 'mail.visitor', with: [
            'name' => $this->lead->name,
            'intro' => __('site.mail.lead_received_intro'),
            'quote' => $this->lead->message,
            'link' => $analysis ? route('analysis.show', ['locale' => $this->lead->locale, 'analysis' => $analysis->uuid]) : null,
        ]);
    }
}
