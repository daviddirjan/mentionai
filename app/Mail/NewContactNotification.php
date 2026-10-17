<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Notification to the administrator (always in English). */
class NewContactNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contact)
    {
        $this->locale('en');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('site.mail.new_contact_subject', ['name' => $this->contact->name]),
            replyTo: [$this->contact->email],
        );
    }

    public function content(): Content
    {
        $rows = [
            'Name' => $this->contact->name,
            'Email' => $this->contact->email,
            'Company' => $this->contact->company,
            'Subject' => $this->contact->subject,
            'Interface language' => $this->contact->locale,
        ];

        return new Content(view: 'mail.admin', with: [
            'intro' => __('site.mail.admin_contact_intro'),
            'rows' => array_filter($rows, fn ($v) => $v !== null && $v !== ''),
            'body' => $this->contact->message,
            'link' => null,
        ]);
    }
}
