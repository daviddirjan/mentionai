<?php

namespace Tests\Feature\Backend;

use App\Contracts\RecommendationBuilder;
use App\Data\Recommendation;
use App\Enums\MentionClassification;
use App\Enums\Priority;
use App\Models\Analysis;
use App\Models\PromptTemplate;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationBuilderTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_PASS = [
        'homepage_status', 'https', 'robots_txt', 'sitemap_xml', 'llms_txt',
        'title_tag', 'meta_description', 'h1_count', 'html_lang', 'canonical', 'robots_meta', 'hreflang', 'open_graph',
        'jsonld_present', 'jsonld_local', 'jsonld_locality_match',
    ];

    /** @param  array<string, string>  $overrides key => status */
    private function checks(array $overrides = []): array
    {
        $checks = [];
        foreach (self::ALL_PASS as $key) {
            $checks[] = ['key' => $key, 'group' => 'access', 'status' => $overrides[$key] ?? 'pass', 'evidence' => 'x', 'params' => []];
        }
        foreach (['GPTBot' => 'gptbot', 'ClaudeBot' => 'claudebot', 'PerplexityBot' => 'perplexitybot'] as $bot => $suffix) {
            $key = 'ai_crawler_'.$suffix;
            $checks[] = ['key' => $key, 'group' => 'ai_crawlers', 'status' => $overrides[$key] ?? 'pass', 'evidence' => 'x', 'params' => ['bot' => $bot]];
        }
        $checks[] = ['key' => 'response_time', 'group' => 'access', 'status' => 'info', 'evidence' => 'GET / → 300 ms', 'params' => ['ms' => 300]];

        return $checks;
    }

    /** @return array<string, Recommendation> */
    private function build(Analysis $analysis): array
    {
        $recs = app(RecommendationBuilder::class)->build($analysis);
        $this->assertContainsOnlyInstancesOf(Recommendation::class, $recs);
        $keys = array_map(fn (Recommendation $r) => $r->key, $recs);
        $this->assertSame($keys, array_values(array_unique($keys)), 'no duplicates');

        return array_combine($keys, $recs);
    }

    /** All visibility rules satisfied: score 100, brand cited, no competitors. */
    private function healthy(array $checks, array $attributes = []): Analysis
    {
        $analysis = Analysis::factory()->audit()->create($attributes + ['site_audit' => $checks]);
        $analysis->brandMentions()->update(['classification' => MentionClassification::Recommended->value]);
        $analysis->responses()->where('status', 'succeeded')->update(['citations' => [['url' => 'https://'.$analysis->domain.'/', 'title' => null]]]);
        $analysis->competitors()->delete();
        $analysis->update(['visibility_score' => 100]);

        return $analysis->refresh();
    }

    public function test_clean_site_and_strong_visibility_only_gets_the_always_rule(): void
    {
        $recs = $this->build($this->healthy($this->checks()));

        $this->assertSame(['third_party_profiles'], array_keys($recs));
        $this->assertSame(Priority::P2, $recs['third_party_profiles']->priority);
        $this->assertSame('60-90', $recs['third_party_profiles']->window);
    }

    public function test_each_check_trigger_maps_to_its_recommendation(): void
    {
        $cases = [
            'homepage_status' => ['fail', 'fix_homepage_status', 'p0', '0-7'],
            'https' => ['fail', 'fix_https', 'p0', '0-7'],
            'robots_txt' => ['fail', 'publish_robots_sitemap', 'p0', '0-7'],
            'sitemap_xml' => ['fail', 'publish_robots_sitemap', 'p0', '0-7'],
            'robots_meta' => ['fail', 'fix_robots_meta', 'p0', '0-7'],
            'html_lang' => ['fail', 'fix_html_lang_h1', 'p0', '0-14'],
            'h1_count' => ['warn', 'fix_html_lang_h1', 'p0', '0-14'],
            'title_tag' => ['warn', 'fix_title_meta', 'p0', '0-14'],
            'meta_description' => ['fail', 'fix_title_meta', 'p0', '0-14'],
            'canonical' => ['warn', 'add_canonical', 'p1', '15-30'],
            'jsonld_present' => ['fail', 'add_local_business_schema', 'p0', '0-21'],
            'jsonld_local' => ['warn', 'add_local_business_schema', 'p0', '0-21'],
            'jsonld_locality_match' => ['warn', 'add_local_business_schema', 'p0', '0-21'],
            'hreflang' => ['warn', 'add_hreflang', 'p1', '15-30'],
            'open_graph' => ['warn', 'add_open_graph', 'p2', '60-90'],
            'llms_txt' => ['warn', 'publish_llms_txt', 'p2', '60-90'],
        ];

        foreach ($cases as $check => [$status, $key, $priority, $window]) {
            $recs = $this->build($this->healthy($this->checks([$check => $status])));
            $this->assertArrayHasKey($key, $recs, $check);
            $this->assertSame($priority, $recs[$key]->priority->value, $check);
            $this->assertSame($window, $recs[$key]->window, $check);
            $this->assertSame("check:{$check}", $recs[$key]->source, $check);
            $this->assertSame([$key, 'third_party_profiles'], array_keys($recs), $check);
        }
    }

    public function test_warn_on_fail_only_triggers_is_ignored(): void
    {
        $recs = $this->build($this->healthy($this->checks(['robots_txt' => 'warn', 'homepage_status' => 'warn'])));

        $this->assertArrayNotHasKey('publish_robots_sitemap', $recs);
        $this->assertArrayNotHasKey('fix_homepage_status', $recs);
    }

    public function test_blocked_ai_crawlers_are_listed_in_one_recommendation(): void
    {
        $recs = $this->build($this->healthy($this->checks(['ai_crawler_gptbot' => 'fail', 'ai_crawler_perplexitybot' => 'fail'])));

        $this->assertSame('GPTBot, PerplexityBot', $recs['allow_ai_crawlers']->params['bots']);
        $this->assertSame(Priority::P0, $recs['allow_ai_crawlers']->priority);
    }

    public function test_local_schema_carries_the_location(): void
    {
        $recs = $this->build($this->healthy($this->checks(['jsonld_local' => 'fail']), ['location' => 'Oradea']));

        $this->assertSame('Oradea', $recs['add_local_business_schema']->params['location']);
    }

    public function test_factory_audit_visibility_rules(): void
    {
        // Factory: score 27.27, openai 0/3 and anthropic 0/2 recommended, Endava/Softvision in every miss, brand cited when recommended.
        $recs = $this->build(Analysis::factory()->audit()->create());

        $this->assertArrayHasKey('case_studies', $recs);
        $this->assertSame('ChatGPT, Claude', $recs['provider_gap']->params['provider']);
        $this->assertSame('Endava, Softvision, Fortech', $recs['competitor_gap']->params['competitors']);
        $this->assertSame('30-60', $recs['competitor_gap']->window);
        $this->assertArrayNotHasKey('earn_citations', $recs);
        $this->assertArrayNotHasKey('local_landing_page', $recs); // no local-category question in the factory set
        $this->assertSame('check:robots_txt', $recs['publish_robots_sitemap']->source);
    }

    public function test_competitor_gap_needs_a_quarter_of_the_misses_and_keeps_three_names(): void
    {
        $analysis = Analysis::factory()->audit()->create();
        $analysis->competitors()->create(['name' => 'Rarely Seen', 'normalized_name' => 'rarely seen', 'mention_count' => 1, 'providers' => ['openai']]);

        $recs = $this->build($analysis);

        $this->assertStringNotContainsString('Rarely Seen', $recs['competitor_gap']->params['competitors']);
        $this->assertCount(3, explode(', ', $recs['competitor_gap']->params['competitors']));
    }

    public function test_earn_citations_when_the_brand_domain_is_never_cited(): void
    {
        $analysis = Analysis::factory()->audit()->create();
        $analysis->responses()->update(['citations' => [['url' => 'https://directory.example/list', 'title' => 'List']]]);

        $this->assertArrayHasKey('earn_citations', $this->build($analysis));
    }

    public function test_local_landing_page_when_a_local_question_was_not_recommended(): void
    {
        $this->seed(PromptTemplateSeeder::class);
        $analysis = Analysis::factory()->audit()->create();
        $local = PromptTemplate::query()->where('category', 'local')->first();
        // Question 3 is not recommended by any provider in the factory set.
        $analysis->questions()->where('position', 3)->update(['prompt_template_id' => $local->id]);

        $recs = $this->build($analysis->refresh());

        $this->assertSame('Oradea', $recs['local_landing_page']->params['location']);
        $this->assertSame(Priority::P1, $recs['local_landing_page']->priority);
        $this->assertSame('15-30', $recs['local_landing_page']->window);

        $analysis->update(['location' => null]);
        $this->assertArrayNotHasKey('local_landing_page', $this->build($analysis->refresh()));
    }

    public function test_sorted_by_priority_then_window_and_always_rule_last(): void
    {
        $analysis = Analysis::factory()->audit()->create(['site_audit' => $this->checks([
            'llms_txt' => 'warn', 'canonical' => 'warn', 'robots_txt' => 'fail', 'html_lang' => 'fail', 'jsonld_local' => 'warn', 'open_graph' => 'fail',
        ])]);

        $recs = array_values($this->build($analysis));

        $rank = fn (Recommendation $r) => [$r->priority->planPhase(), (int) explode('-', $r->window)[0], (int) explode('-', $r->window)[1]];
        for ($i = 1; $i < count($recs); $i++) {
            $this->assertLessThanOrEqual(0, $rank($recs[$i - 1]) <=> $rank($recs[$i]), $recs[$i]->key);
        }
        $this->assertSame('third_party_profiles', end($recs)->key);
        $this->assertSame(['publish_robots_sitemap', 'fix_html_lang_h1', 'add_local_business_schema'], array_map(fn ($r) => $r->key, array_slice($recs, 0, 3)));
    }

    public function test_recommendations_round_trip_through_the_model(): void
    {
        $analysis = Analysis::factory()->audit()->create();
        $recs = app(RecommendationBuilder::class)->build($analysis);

        $analysis->update(['recommendations' => array_map(fn (Recommendation $r) => $r->toArray(), $recs)]);

        $this->assertEquals($recs, $analysis->refresh()->recommendationList());
    }
}
