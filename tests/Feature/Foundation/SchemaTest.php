<?php

namespace Tests\Feature\Foundation;

use App\Enums\AnalysisStatus;
use App\Models\Analysis;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_factory_builds_a_consistent_analysis(): void
    {
        $analysis = Analysis::factory()->completed()->create();

        $this->assertSame(AnalysisStatus::Completed, $analysis->status);
        $this->assertSame(12, $analysis->responses()->count());
        $this->assertSame(11, $analysis->successful_tests);
        $this->assertEquals(27.27, (float) $analysis->visibility_score);
        $this->assertSame(2, $analysis->provider_scores['perplexity']['recommended']);
    }

    public function test_analysis_uses_random_uuid_route_key(): void
    {
        $analysis = Analysis::factory()->create();

        $this->assertSame('uuid', $analysis->getRouteKeyName());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-/', $analysis->uuid);
    }

    public function test_settings_overlay_database_values_on_config_defaults(): void
    {
        $settings = app(Settings::class);

        $this->assertSame(3, $settings->get('query_count'));

        $settings->set('query_count', 5);

        $this->assertSame(5, settings('query_count'));
    }
}
