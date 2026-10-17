<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Confirmation sent to the visitor after a contact message, in their interface language. */
class ContactReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contact)
    {
        $this->locale($contact->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('site.mail.contact_received_subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.visitor', with: [
            'name' => $this->contact->name,
            'intro' => __('site.mail.contact_received_intro'),
            'quote' => $this->contact->message,
            'link' => null,
        ]);
    }
}
