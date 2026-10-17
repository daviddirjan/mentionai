<?php

namespace Tests\Feature\Frontend;

use App\Enums\AnalysisStatus;
use App\Livewire\AnalysisResults;
use App\Models\Analysis;
use Livewire\Livewire;

class ResultsPageTest extends FrontendTestCase
{
    private function url(Analysis $analysis, string $locale = 'en'): string
    {
        return "/{$locale}/analysis/{$analysis->uuid}";
    }

    public function test_completed_analysis_renders_score_providers_competitors_and_questions(): void
    {
        $analysis = Analysis::factory()->completed()->create(['company_name' => 'Axiontic']);

        $this->get($this->url($analysis))
            ->assertOk()
            ->assertSee('Axiontic')
            ->assertSee('27.3')
            ->assertSee('11 successful tests')
            ->assertSee('not an official ranking', false)
            ->assertSee('ChatGPT')->assertSee('Gemini')->assertSee('Claude')->assertSee('Perplexity')
            ->assertSee('Endava')->assertSee('Softvision')
            ->assertSee('Recommend software development companies in Romania')
            ->assertSee('Get Full GEO Audit')
            ->assertSee('Talk to a GEO Expert')
            ->assertSee('Your competitors may be getting recommended')
            ->assertDontSee('DEMO —', false);
    }

    public function test_failed_provider_response_is_shown_as_failed_not_as_not_mentioned(): void
    {
        $analysis = Analysis::factory()->completed()->create();

        $this->get($this->url($analysis))
            ->assertSee('No answer: the request failed.')
            ->assertSee('1 failed tests', false);
    }

    public function test_high_score_shows_the_high_visibility_message(): void
    {
        $analysis = Analysis::factory()->completed()->create();
        $analysis->update(['visibility_score' => 80]);

        $this->get($this->url($analysis))
            ->assertSee('Discover how to maintain and expand your visibility')
            ->assertDontSee('Your competitors may be getting recommended');
    }

    public function test_in_progress_analysis_shows_real_status_and_polls(): void
    {
        $analysis = Analysis::factory()->create(['status' => AnalysisStatus::QueryingProviders]);

        $this->get($this->url($analysis))
            ->assertOk()
            ->assertSee('Analysis in progress')
            ->assertSee('Asking the AI assistants')
            ->assertSee('wire:poll', false)
            ->assertDontSee('visibility score', false);
    }

    public function test_polling_stops_once_the_analysis_is_finished(): void
    {
        $analysis = Analysis::factory()->completed()->create();

        $this->get($this->url($analysis))->assertDontSee('wire:poll', false);
    }

    public function test_failed_analysis_renders_failed_state_without_internal_error(): void
    {
        $analysis = Analysis::factory()->failed()->create();

        $this->get($this->url($analysis))
            ->assertOk()
            ->assertSee('The analysis could not be completed')
            ->assertDontSee('All providers failed.');
    }

    public function test_demo_analysis_has_a_prominent_banner(): void
    {
        $analysis = Analysis::factory()->demo()->completed()->create();

        $this->get($this->url($analysis))->assertOk()->assertSee('DEMO —', false);
    }

    public function test_cached_notice_shows_date_when_reused(): void
    {
        $analysis = Analysis::factory()->completed()->create();

        session()->put('analysis_reused', $analysis->uuid);
        Livewire::test(AnalysisResults::class, ['uuid' => $analysis->uuid])
            ->assertSee('recent analysis for the same business')
            ->assertSee($analysis->completed_at->locale('en')->isoFormat('LL'));

        session()->forget('analysis_reused');
        Livewire::test(AnalysisResults::class, ['uuid' => $analysis->uuid])
            ->assertDontSee('recent analysis for the same business');
    }

    public function test_unknown_or_malformed_uuid_returns_404(): void
    {
        $this->get('/en/analysis/'.fake()->uuid())->assertNotFound();
        $this->get('/en/analysis/not-a-uuid')->assertNotFound();
        $this->get('/en/analysis/1')->assertNotFound();
    }

    public function test_results_page_is_noindex_and_has_no_hreflang(): void
    {
        $analysis = Analysis::factory()->completed()->create();

        $this->get($this->url($analysis))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('rel="alternate"', false);
    }

    public function test_internal_data_is_never_leaked(): void
    {
        $analysis = Analysis::factory()->completed()->create(['ip_hash' => str_repeat('a', 64)]);
        $analysis->responses()->update(['raw_response' => ['secret' => 'RAW-PROVIDER-PAYLOAD-123']]);

        $response = $this->get($this->url($analysis))->assertOk();

        $response->assertDontSee('RAW-PROVIDER-PAYLOAD-123')
            ->assertDontSee(str_repeat('a', 64))
            ->assertDontSee('raw_response')
            ->assertDontSee('"id":'.$analysis->id.',', false);
    }

    public function test_ai_text_is_escaped_and_only_safe_markup_is_rendered(): void
    {
        $analysis = Analysis::factory()->completed()->create();
        $analysis->responses()->where('provider', 'gemini')->update([
            'response_text' => "<script>alert(1)</script> **bold** [ok](https://example.com) [bad](javascript:alert(1))\n- item one",
        ]);

        $this->get($this->url($analysis))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('<strong>bold</strong>', false)
            ->assertSee('<a href="https://example.com"', false)
            ->assertDontSee('href="javascript:', false)
            ->assertSee('<li>item one</li>', false);
    }
}
