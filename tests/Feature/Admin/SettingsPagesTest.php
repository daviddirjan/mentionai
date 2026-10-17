<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\ApiCosts;
use App\Filament\Pages\WebsiteSettings;
use App\Models\Analysis;
use App\Models\ApiUsageLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

class SettingsPagesTest extends AdminTestCase
{
    public function test_pages_load(): void
    {
        $this->get('/admin/website-settings')->assertOk()->assertSee('Website settings');
        $this->get('/admin/api-costs')->assertOk()->assertSee('not invoices');
    }

    public function test_website_settings_are_persisted_through_settings(): void
    {
        Livewire::test(WebsiteSettings::class)
            ->assertFormSet(['site_name' => 'MentionAI', 'report_price' => 99.0])
            ->fillForm([
                'site_name' => 'Acme AI',
                'tagline' => 'See what AI says',
                'contact_email' => 'hi@acme.test',
                'company_info' => 'Acme SRL',
                'social_links' => ['linkedin' => 'https://linkedin.com/company/acme'],
                'seo_title' => 'Acme AI visibility',
                'meta_description' => 'Check your visibility.',
                'default_locale' => 'ro',
                'report_price' => 149.5,
                'report_currency' => 'ron',
                'maintenance_mode' => true,
                'data_retention_days' => 180,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Acme AI', settings('site_name'));
        $this->assertSame('ro', settings('default_locale'));
        $this->assertSame(14950, settings('report_price'));
        $this->assertSame('RON', settings('report_currency'));
        $this->assertTrue(settings('maintenance_mode'));
        $this->assertSame(180, settings('data_retention_days'));
        $this->assertSame(['linkedin' => 'https://linkedin.com/company/acme'], settings('social_links'));
        $this->assertDatabaseHas('settings', ['key' => 'site_name']);
    }

    public function test_website_settings_reject_unknown_locale_and_bad_email(): void
    {
        Livewire::test(WebsiteSettings::class)
            ->fillForm(['default_locale' => 'xx', 'contact_email' => 'nope'])
            ->call('save')
            ->assertHasFormErrors(['default_locale', 'contact_email']);
    }

    public function test_logo_upload_is_stored_on_the_public_disk(): void
    {
        Storage::fake('public');

        Livewire::test(WebsiteSettings::class)
            ->fillForm(['logo_path' => UploadedFile::fake()->image('logo.png', 120, 40)])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = settings('logo_path');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_limits_are_saved_to_settings(): void
    {
        Livewire::test(ApiCosts::class)
            ->fillForm([
                'analyses_enabled' => false,
                'max_analyses_per_day' => 50,
                'max_cost_per_analysis' => 0.25,
                'monthly_budget' => 40,
                'free_analyses_per_domain' => 2,
                'domain_window_hours' => 48,
                'ip_analyses_per_hour' => 3,
                'cache_hours' => 12,
                'query_count' => 5,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(settings('analyses_enabled'));
        $this->assertSame(50, settings('max_analyses_per_day'));
        $this->assertSame(0.25, settings('max_cost_per_analysis'));
        $this->assertEquals(40, settings('monthly_budget'));
        $this->assertSame(5, settings('query_count'));
    }

    public function test_limits_validation(): void
    {
        Livewire::test(ApiCosts::class)
            ->fillForm(['query_count' => 0, 'max_analyses_per_day' => -1])
            ->call('save')
            ->assertHasFormErrors(['query_count', 'max_analyses_per_day']);
    }

    public function test_cost_report_separates_demo_usage(): void
    {
        $analysis = Analysis::factory()->completed()->create(['estimated_cost' => 0.05]);
        $this->log($analysis, false, 0.05);
        $this->log($analysis, true, 9.0);

        Livewire::test(ApiCosts::class)
            ->assertSee('0.0500')
            ->assertDontSee('9.0000')
            ->set('includeDemo', true)
            ->assertSee('9.0500');
    }

    private function log(Analysis $analysis, bool $demo, float $cost): void
    {
        ApiUsageLog::create([
            'analysis_id' => $analysis->id,
            'provider' => 'openai',
            'model' => 'm',
            'operation' => 'query',
            'status' => 'succeeded',
            'is_demo' => $demo,
            'input_tokens' => 100,
            'output_tokens' => 200,
            'estimated_cost' => $cost,
            'created_at' => now(),
        ]);
    }
}
