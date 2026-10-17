<?php

namespace Tests\Feature\Backend;

use App\Enums\MentionClassification;
use App\Enums\ResponseStatus;
use App\Models\Analysis;
use App\Services\Scoring\ScoreCalculator;
use Database\Seeders\AiProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AiProviderSeeder::class);
    }

    public function test_matches_the_factory_reference_analysis(): void
    {
        $analysis = Analysis::factory()->completed()->create();
        $expected = $analysis->only(['visibility_score', 'successful_tests', 'failed_tests', 'recommendation_count', 'provider_scores']);

        $result = app(ScoreCalculator::class)->calculate($analysis);

        $this->assertEquals(27.27, $result['visibility_score']);
        $this->assertSame(11, $result['successful_tests']);
        $this->assertSame(1, $result['failed_tests']);
        $this->assertSame(3, $result['recommendation_count']);
        $this->assertEquals($expected['provider_scores'], $result['provider_scores']);
        $this->assertSame(['recommended' => 0, 'successful' => 2, 'total' => 3, 'percentage' => 0.0], $result['provider_scores']['anthropic']);
        $this->assertEquals(66.67, $result['provider_scores']['perplexity']['percentage']);
    }

    public function test_failed_and_skipped_responses_are_excluded_from_the_denominator(): void
    {
        $analysis = Analysis::factory()->completed()->create();
        $analysis->responses()->where('provider', 'openai')->take(2)->get()->each->update(['status' => ResponseStatus::Skipped, 'error_code' => 'budget_exceeded']);

        $result = app(ScoreCalculator::class)->apply($analysis)->refresh();

        $this->assertSame(9, $result->successful_tests);
        $this->assertSame(3, $result->failed_tests);
        $this->assertEquals(33.33, $result->visibility_score);
        $this->assertSame(1, $result->provider_scores['openai']['successful']);
        $this->assertSame(3, $result->provider_scores['openai']['total']);
    }

    public function test_score_is_null_when_nothing_succeeded(): void
    {
        $analysis = Analysis::factory()->completed()->create();
        $analysis->responses()->update(['status' => ResponseStatus::Failed]);

        $result = app(ScoreCalculator::class)->calculate($analysis);

        $this->assertNull($result['visibility_score']);
        $this->assertSame(0, $result['successful_tests']);
        $this->assertSame(12, $result['failed_tests']);
        $this->assertSame(0.0, $result['provider_scores']['openai']['percentage']);
    }

    public function test_only_recommended_counts_toward_the_score(): void
    {
        $analysis = Analysis::factory()->completed()->create();
        $analysis->brandMentions()->update(['classification' => MentionClassification::Mentioned->value, 'evidence' => []]);

        $result = app(ScoreCalculator::class)->calculate($analysis);

        $this->assertEquals(0, $result['visibility_score']);
        $this->assertSame(0, $result['recommendation_count']);
        $this->assertSame(11, $result['mention_count']);
    }

    public function test_uncertain_and_not_mentioned_never_count(): void
    {
        $analysis = Analysis::factory()->completed()->create();
        $analysis->brandMentions()->update(['classification' => MentionClassification::Uncertain->value, 'evidence' => []]);

        $result = app(ScoreCalculator::class)->calculate($analysis);

        $this->assertEquals(0, $result['visibility_score']);
        $this->assertSame(0, $result['mention_count']);
        $this->assertSame(0, $result['citation_count']);
    }

    public function test_cited_only_counts_as_citation(): void
    {
        $analysis = Analysis::factory()->completed()->create();
        $analysis->brandMentions()->update(['classification' => MentionClassification::CitedOnly->value, 'evidence' => []]);

        $result = app(ScoreCalculator::class)->calculate($analysis);

        $this->assertSame(11, $result['citation_count']);
        $this->assertSame(0, $result['recommendation_count']);
    }
}
