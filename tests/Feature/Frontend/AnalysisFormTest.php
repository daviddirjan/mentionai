<?php

namespace Tests\Feature\Frontend;

use App\Exceptions\AnalysisRejected;
use App\Livewire\AnalysisForm;
use App\Models\Analysis;
use Livewire\Livewire;

class AnalysisFormTest extends FrontendTestCase
{
    private function filled(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(AnalysisForm::class)
            ->set('companyName', 'Axiontic')
            ->set('websiteUrl', 'axiontic.com')
            ->set('market', 'RO')
            ->set('location', ' Cluj-Napoca ')
            ->set('industry', 'Software Development')
            ->set('language', 'ro');
    }

    public function test_landing_page_contains_the_form_and_cta(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('Is AI recommending your business?')
            ->assertSee('Check Your AI Visibility — Free')
            ->assertSee('Analyze My Business')
            ->assertSee('href="#analyze"', false)
            ->assertSee('99 RON');
    }

    public function test_required_fields_are_validated(): void
    {
        Livewire::test(AnalysisForm::class)
            ->call('submit')
            ->assertHasErrors(['companyName' => 'required', 'websiteUrl' => 'required', 'market' => 'required']);

        $this->assertNull($this->starter->received);
    }

    public function test_invalid_url_market_and_language_are_rejected(): void
    {
        $this->filled()
            ->set('websiteUrl', 'not a url')
            ->set('market', 'ZZ')
            ->set('language', 'xx')
            ->call('submit')
            ->assertHasErrors(['websiteUrl', 'market', 'language']);

        $this->filled()->set('websiteUrl', 'http://127.0.0.1')->call('submit')->assertHasErrors(['websiteUrl']);
        $this->assertNull($this->starter->received);
    }

    public function test_validation_messages_are_localized(): void
    {
        app()->setLocale('de');

        $component = Livewire::test(AnalysisForm::class)->call('submit');

        $this->assertStringContainsString('Firmenname', $component->errors()->first('companyName'));
    }

    public function test_successful_submission_calls_the_starter_and_redirects(): void
    {
        $this->starter->analysis = Analysis::factory()->create();

        $this->filled()
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('analysis.show', ['locale' => 'en', 'analysis' => $this->starter->analysis->uuid]));

        $input = $this->starter->received;
        $this->assertSame('Axiontic', $input->companyName);
        $this->assertSame('axiontic.com', $input->websiteUrl);
        $this->assertSame('RO', $input->market);
        $this->assertSame('ro', $input->language);
        $this->assertSame('Software Development', $input->industry);
        $this->assertSame('Cluj-Napoca', $input->location);
        $this->assertSame('en', $input->interfaceLocale);
        $this->assertNull($input->turnstileToken);
    }

    public function test_location_is_optional_and_length_limited(): void
    {
        $this->starter->analysis = Analysis::factory()->create();

        $this->filled()->set('location', str_repeat('a', 121))->call('submit')->assertHasErrors(['location' => 'max']);
        $this->filled()->set('location', '')->call('submit')->assertHasNoErrors();
        $this->assertNull($this->starter->received->location);

        $this->get('/ro')->assertSee('Oraș / județ / regiune');
    }

    public function test_turnstile_token_is_required_and_forwarded_when_configured(): void
    {
        config(['mentionai.turnstile.site_key' => 'site-key']);

        $this->filled()->call('submit')->assertHasErrors(['turnstileToken']);
        $this->get('/en')->assertSee('challenges.cloudflare.com/turnstile', false);

        $this->filled()->set('turnstileToken', 'tok')->call('submit')->assertHasNoErrors();
        $this->assertSame('tok', $this->starter->received->turnstileToken);
    }

    public function test_reused_result_flashes_a_notice_marker(): void
    {
        $this->starter->analysis = Analysis::factory()->completed()->create();
        $this->starter->reused = true;

        $this->filled()->call('submit');

        $this->assertSame($this->starter->analysis->uuid, session('analysis_reused'));
    }

    public function test_every_rejection_reason_is_rendered(): void
    {
        $reasons = (new \ReflectionClass(AnalysisRejected::class))->getConstants();

        foreach ($reasons as $reason) {
            $this->starter->reject = new AnalysisRejected($reason);

            $this->filled()
                ->call('submit')
                ->assertSee(__('analysis.rejected.'.$reason))
                ->assertNoRedirect();
        }
    }

    public function test_domain_limit_links_to_the_existing_analysis(): void
    {
        $existing = Analysis::factory()->completed()->create();
        $this->starter->reject = new AnalysisRejected(AnalysisRejected::DOMAIN_LIMIT, $existing->uuid);

        $this->filled()
            ->call('submit')
            ->assertSee(__('analysis.rejected.domain_limit'))
            ->assertSee(route('analysis.show', ['locale' => 'en', 'analysis' => $existing->uuid]), false);
    }

    public function test_honeypot_blocks_bots_silently(): void
    {
        $this->filled()->set('nickname', 'bot')->call('submit')->assertNoRedirect();

        $this->assertNull($this->starter->received);
    }
}
