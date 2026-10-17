<?php

namespace App\Livewire;

use App\Livewire\Concerns\UsesLocale;
use App\Mail\ContactReceived;
use App\Mail\NewContactNotification;
use App\Models\ContactMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ContactForm extends Component
{
    use UsesLocale;

    public const SUBJECTS = ['general', 'geo_audit', 'data_deletion', 'other'];

    public string $name = '';

    public string $email = '';

    public string $company = '';

    public string $subject = 'general';

    public string $message = '';

    /** Honeypot: real visitors never see or fill this. */
    public string $nickname = '';

    public bool $sent = false;

    public function mount(): void
    {
        $preset = request()->query('subject');
        if (is_string($preset) && in_array($preset, self::SUBJECTS, true)) {
            $this->subject = $preset;
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'company' => ['nullable', 'string', 'max:160'],
            'subject' => ['required', 'in:'.implode(',', self::SUBJECTS)],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('site.contact.name'),
            'email' => __('site.contact.email'),
            'company' => __('site.contact.company'),
            'subject' => __('site.contact.subject'),
            'message' => __('site.contact.message'),
        ];
    }

    public function submit(): void
    {
        $this->validate();

        if ($this->nickname !== '') {
            $this->sent = true;

            return;
        }

        $key = 'contact:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('form', __('site.contact.rate_limited'));

            return;
        }
        RateLimiter::hit($key, 3600);

        $contact = ContactMessage::create([
            'name' => trim($this->name),
            'email' => trim($this->email),
            'company' => trim($this->company) ?: null,
            'subject' => __('site.contact.subjects.'.$this->subject, [], 'en'),
            'message' => trim($this->message),
            'locale' => $this->locale,
            'ip_hash' => $this->ipHash(),
        ]);

        if ($adminEmail = config('mentionai.admin_email')) {
            Mail::to($adminEmail)->queue(new NewContactNotification($contact));
        }
        Mail::to($contact->email)->queue(new ContactReceived($contact));

        $this->reset(['name', 'email', 'company', 'message']);
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.contact-form');
    }
}
