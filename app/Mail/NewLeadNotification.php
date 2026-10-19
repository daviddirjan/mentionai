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

/** Notification to the administrator (always in English). */
class NewLeadNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead)
    {
        $this->locale('en');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('site.mail.new_lead_subject', ['name' => $this->lead->name !== '' ? $this->lead->name : $this->lead->email]),
            replyTo: [$this->lead->email],
        );
    }

    public function content(): Content
    {
        $analysis = $this->lead->analysis_id ? Analysis::find($this->lead->analysis_id) : null;

        $rows = [
            'Name' => $this->lead->name,
            'Company' => $this->lead->company,
            'Email' => $this->lead->email,
            'Phone' => $this->lead->phone,
            'Website' => $this->lead->website,
            'Interface language' => $this->lead->locale,
        ];
        if ($analysis) {
            $rows['Analyzed company'] = $analysis->company_name;
            $rows['Visibility score'] = $analysis->visibility_score !== null ? $analysis->visibility_score.'%' : null;
        }

        return new Content(view: 'mail.admin', with: [
            'intro' => __('site.mail.admin_lead_intro'),
            'rows' => array_filter($rows, fn ($v) => $v !== null && $v !== ''),
            'body' => $this->lead->message,
            'link' => $analysis ? route('analysis.show', ['locale' => 'en', 'analysis' => $analysis->uuid]) : null,
        ]);
    }
}
