<?php

namespace Tests\Feature\Frontend;

use App\Enums\AnalysisStatus;
use App\Enums\MentionClassification;
use App\Exceptions\AnalysisRejected;
use App\Livewire\AnalysisForm;
use App\Models\Analysis;
use Livewire\Livewire;

class LocalizationTest extends FrontendTestCase
{
    public function test_root_redirects_by_accept_language(): void
    {
        $this->get('/', ['Accept-Language' => 'ro-RO,ro;q=0.9,en;q=0.5'])->assertRedirect('/ro');
        $this->get('/', ['Accept-Language' => 'de-AT,de;q=0.9'])->assertRedirect('/de');
        $this->get('/', ['Accept-Language' => 'pt-BR'])->assertRedirect('/pt');
    }

    public function test_root_defaults_to_english(): void
    {
        $this->get('/')->assertRedirect('/en');
        $this->get('/', ['Accept-Language' => 'ja-JP,ja;q=0.9'])->assertRedirect('/en');
    }

    public function test_every_locale_renders_the_landing_page_translated(): void
    {
        foreach (config('mentionai.locales') as $locale) {
            $this->get("/{$locale}")
                ->assertOk()
                ->assertSee(__('site.hero.title', [], $locale), false)
                ->assertSee('lang="'.$locale.'"', false)
                ->assertSee('© MentionAI. Developed by Axiontic SRL.');
        }
    }

    public function test_unknown_locale_is_not_found(): void
    {
        $this->get('/xx')->assertNotFound();
    }

    public function test_hreflang_alternates_are_present(): void
    {
        $response = $this->get('/fr')->assertOk();

        foreach (config('mentionai.locales') as $locale) {
            $response->assertSee('hreflang="'.$locale.'" href="'.url("/{$locale}").'"', false);
        }
        $response->assertSee('hreflang="x-default" href="'.url('/en').'"', false);
        $response->assertSee('<link rel="canonical" href="'.url('/fr').'">', false);
    }

    public function test_language_switcher_preserves_the_current_page(): void
    {
        $this->get('/ro/privacy-policy')
            ->assertOk()
            ->assertSee('href="'.url('/de/privacy-policy').'"', false);
    }

    public function test_all_locales_have_the_same_translation_keys_as_english(): void
    {
        foreach (['site', 'analysis', 'audit'] as $file) {
            $expected = $this->keys(require lang_path("en/{$file}.php"));
            foreach (config('mentionai.locales') as $locale) {
                $actual = $this->keys(require lang_path("{$locale}/{$file}.php"));
                $this->assertSame([], array_diff($expected, $actual), "{$locale}/{$file}.php is missing keys");
                $this->assertSame([], array_diff($actual, $expected), "{$locale}/{$file}.php has unknown keys");
            }
        }
    }

    public function test_shared_translation_keys_exist_for_every_enum_value(): void
    {
        foreach (config('mentionai.locales') as $locale) {
            foreach (AnalysisStatus::cases() as $status) {
                $this->assertNotSame($status->translationKey(), __($status->translationKey(), [], $locale));
            }
            foreach (MentionClassification::cases() as $case) {
                $this->assertNotSame($case->translationKey(), __($case->translationKey(), [], $locale));
            }
            foreach ((new \ReflectionClass(AnalysisRejected::class))->getConstants() as $reason) {
                $this->assertNotSame("analysis.rejected.{$reason}", __("analysis.rejected.{$reason}", [], $locale));
            }
        }
    }

    public function test_analysis_language_is_independent_from_interface_locale(): void
    {
        app()->setLocale('es');
        Livewire::test(AnalysisForm::class)->assertSet('language', 'es');

        $analysis = Analysis::factory()->completed()->create(['language' => 'ro', 'market' => 'RO']);
        $this->get('/es/analysis/'.$analysis->uuid)
            ->assertOk()
            ->assertSee(\Locale::getDisplayRegion('-RO', 'es'));
    }

    /** @return list<string> */
    private function keys(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            is_array($value)
                ? array_push($keys, ...$this->keys($value, "{$prefix}{$key}."))
                : $keys[] = "{$prefix}{$key}";
        }

        return $keys;
    }
}
