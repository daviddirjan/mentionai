<?php

namespace Tests\Feature\Frontend;

use App\Enums\ContactMessageStatus;
use App\Enums\LeadStatus;
use App\Livewire\ContactForm;
use App\Livewire\LeadForm;
use App\Mail\ContactReceived;
use App\Mail\LeadReceived;
use App\Mail\NewContactNotification;
use App\Mail\NewLeadNotification;
use App\Models\Analysis;
use App\Models\ContactMessage;
use App\Models\Lead;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

class LeadAndContactTest extends FrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['mentionai.admin_email' => 'admin@example.com']);
        RateLimiter::clear('lead:127.0.0.1');
        RateLimiter::clear('contact:127.0.0.1');
    }

    private function lead(Analysis $analysis): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(LeadForm::class, ['analysisUuid' => $analysis->uuid, 'company' => 'Axiontic', 'website' => 'https://axiontic.com'])
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->set('message', 'Please contact me.');
    }

    public function test_lead_is_stored_linked_to_the_analysis_and_mails_are_queued(): void
    {
        $analysis = Analysis::factory()->completed()->create();

        $this->lead($analysis)->call('submit')->assertHasNoErrors()->assertSet('sent', true)->assertSee('Request received');

        $lead = Lead::firstOrFail();
        $this->assertSame($analysis->id, $lead->analysis_id);
        $this->assertSame('Axiontic', $lead->company);
        $this->assertSame('https://axiontic.com', $lead->website);
        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertNotNull($lead->ip_hash);
        $this->assertNotSame('127.0.0.1', $lead->ip_hash);

        Mail::assertQueued(NewLeadNotification::class, fn ($m) => $m->hasTo('admin@example.com'));
        Mail::assertQueued(LeadReceived::class, fn ($m) => $m->hasTo('jane@example.com'));
    }

    public function test_lead_validation(): void
    {
        $analysis = Analysis::factory()->create();

        Livewire::test(LeadForm::class, ['analysisUuid' => $analysis->uuid])
            ->set('email', 'nope')
            ->call('submit')
            ->assertHasErrors(['name' => 'required', 'email' => 'email', 'message' => 'required']);

        $this->assertSame(0, Lead::count());
    }

    public function test_lead_honeypot_creates_nothing(): void
    {
        $analysis = Analysis::factory()->create();

        $this->lead($analysis)->set('nickname', 'bot')->call('submit');

        $this->assertSame(0, Lead::count());
        Mail::assertNothingQueued();
    }

    public function test_lead_form_is_rate_limited(): void
    {
        $analysis = Analysis::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->lead($analysis)->call('submit')->assertSet('sent', true);
        }
        $this->lead($analysis)->call('submit')->assertHasErrors('form')->assertSet('sent', false);

        $this->assertSame(5, Lead::count());
    }

    public function test_audit_cta_prefills_the_lead_message(): void
    {
        $analysis = Analysis::factory()->completed()->create();

        Livewire::test(LeadForm::class, ['analysisUuid' => $analysis->uuid])
            ->dispatch('prefill-lead', topic: 'audit')
            ->assertSet('message', __('site.lead.audit_message'));
    }

    public function test_lead_form_is_on_the_results_page(): void
    {
        $analysis = Analysis::factory()->completed()->create();

        $this->get("/en/analysis/{$analysis->uuid}")->assertSee('Talk to a GEO expert')->assertSee('Send request');
    }

    public function test_contact_message_is_stored_and_mails_are_queued(): void
    {
        Livewire::test(ContactForm::class)
            ->set('name', 'John')
            ->set('email', 'john@example.com')
            ->set('subject', 'geo_audit')
            ->set('message', 'Tell me more about GEO audits.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $message = ContactMessage::firstOrFail();
        $this->assertSame('GEO audit', $message->subject);
        $this->assertSame(ContactMessageStatus::New, $message->status);
        $this->assertSame('en', $message->locale);

        Mail::assertQueued(NewContactNotification::class, fn ($m) => $m->hasTo('admin@example.com'));
        Mail::assertQueued(ContactReceived::class, fn ($m) => $m->hasTo('john@example.com'));
    }

    public function test_contact_validation_and_rate_limit(): void
    {
        Livewire::test(ContactForm::class)->call('submit')->assertHasErrors(['name', 'email', 'message']);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(ContactForm::class)->set('name', 'A')->set('email', 'a@example.com')->set('message', 'Hi')->call('submit')->assertSet('sent', true);
        }
        Livewire::test(ContactForm::class)->set('name', 'A')->set('email', 'a@example.com')->set('message', 'Hi')->call('submit')->assertHasErrors('form');

        $this->assertSame(5, ContactMessage::count());
    }

    public function test_data_deletion_subject_can_be_preset_from_the_url(): void
    {
        $this->get('/en/contact?subject=data_deletion')->assertOk()->assertSee('Data deletion request');

        Livewire::withQueryParams(['subject' => 'data_deletion'])
            ->test(ContactForm::class)
            ->assertSet('subject', 'data_deletion');
    }

    public function test_mailables_render_localized_content(): void
    {
        $lead = Lead::factory()->create(['locale' => 'ro', 'name' => 'Ana', 'analysis_id' => null]);

        $mail = new LeadReceived($lead);

        $mail->assertSeeInHtml('Ana');
        $mail->assertSeeInHtml(__('site.mail.lead_received_intro', [], 'ro'));
        $this->assertSame('ro', $mail->locale);
    }
}
