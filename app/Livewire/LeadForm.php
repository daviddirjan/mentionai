<?php

namespace App\Livewire;

use App\Enums\LeadStatus;
use App\Livewire\Concerns\UsesLocale;
use App\Mail\LeadReceived;
use App\Mail\NewLeadNotification;
use App\Models\Analysis;
use App\Models\Lead;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class LeadForm extends Component
{
    use UsesLocale;

    #[Locked]
    public string $analysisUuid = '';

    public string $name = '';

    public string $company = '';

    public string $email = '';

    public string $phone = '';

    public string $website = '';

    public string $message = '';

    /** Honeypot: real visitors never see or fill this. */
    public string $nickname = '';

    public bool $sent = false;

    public function mount(string $analysisUuid, string $company = '', string $website = ''): void
    {
        $this->analysisUuid = $analysisUuid;
        $this->company = $company;
        $this->website = $website;
    }

    #[On('prefill-lead')]
    public function prefill(string $topic = 'audit'): void
    {
        if ($topic === 'audit' && ! $this->sent) {
            $this->message = __('site.lead.audit_message');
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:2048'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('site.lead.name'),
            'company' => __('site.lead.company'),
            'email' => __('site.lead.email'),
            'phone' => __('site.lead.phone'),
            'website' => __('site.lead.website'),
            'message' => __('site.lead.message'),
        ];
    }

    public function submit(): void
    {
        $this->validate();

        if ($this->nickname !== '') {
            $this->sent = true;

            return;
        }

        $key = 'lead:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('form', __('site.lead.rate_limited'));

            return;
        }
        RateLimiter::hit($key, 3600);

        $analysis = Analysis::where('uuid', $this->analysisUuid)->first();

        $lead = Lead::create([
            'analysis_id' => $analysis?->id,
            'name' => trim($this->name),
            'company' => trim($this->company) ?: null,
            'email' => trim($this->email),
            'phone' => trim($this->phone) ?: null,
            'website' => trim($this->website) ?: null,
            'message' => trim($this->message),
            'status' => LeadStatus::New,
            'locale' => $this->locale,
            'ip_hash' => $this->ipHash(),
        ]);

        if ($adminEmail = config('mentionai.admin_email')) {
            Mail::to($adminEmail)->queue(new NewLeadNotification($lead));
        }
        Mail::to($lead->email)->queue(new LeadReceived($lead));

        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.lead-form');
    }
}
