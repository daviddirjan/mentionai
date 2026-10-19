<?php

namespace Database\Factories;

use App\Enums\AnalysisStatus;
use App\Enums\AnalysisTier;
use App\Enums\MentionClassification;
use App\Enums\ProviderSlug;
use App\Enums\ResponseStatus;
use App\Models\AiProvider;
use App\Models\Analysis;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Analysis>
 */
class AnalysisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'company_name' => fn (array $a) => Company::find($a['company_id'])->name,
            'domain' => fn (array $a) => Company::find($a['company_id'])->domain,
            'website_url' => fn (array $a) => 'https://'.$a['domain'],
            'market' => 'RO',
            'language' => 'ro',
            'industry' => 'Software Development',
            'interface_locale' => 'en',
            'status' => AnalysisStatus::Pending,
            'is_demo' => false,
            'config_hash' => fn (array $a) => hash('sha256', $a['domain'].'|'.$a['market'].'|'.$a['language'].'|test'),
        ];
    }

    /**
     * A completed GEO audit: completed() data plus a site audit (robots/sitemap missing, 2 H1, no lang),
     * rule recommendations and a narrative. Questions/responses are the same 3x4 set as completed().
     */
    public function audit(): static
    {
        return $this->completed()->state(fn () => [
            'tier' => AnalysisTier::Audit,
            'location' => 'Oradea',
            'site_audit' => [
                ['key' => 'homepage_status', 'group' => 'access', 'status' => 'pass', 'evidence' => 'GET / → 200', 'params' => []],
                ['key' => 'robots_txt', 'group' => 'access', 'status' => 'fail', 'evidence' => 'GET /robots.txt → 404', 'params' => []],
                ['key' => 'sitemap_xml', 'group' => 'access', 'status' => 'fail', 'evidence' => 'GET /sitemap.xml → 404', 'params' => []],
                ['key' => 'llms_txt', 'group' => 'access', 'status' => 'warn', 'evidence' => 'GET /llms.txt → 404', 'params' => []],
                ['key' => 'ai_crawler_gptbot', 'group' => 'ai_crawlers', 'status' => 'pass', 'evidence' => 'No robots.txt rule blocks GPTBot', 'params' => ['bot' => 'GPTBot']],
                ['key' => 'h1_count', 'group' => 'structure', 'status' => 'warn', 'evidence' => '2 × <h1>', 'params' => ['count' => 2]],
                ['key' => 'html_lang', 'group' => 'structure', 'status' => 'fail', 'evidence' => '<html> without lang', 'params' => []],
                ['key' => 'jsonld_local', 'group' => 'entity', 'status' => 'warn', 'evidence' => 'JSON-LD @type Organization, no addressLocality', 'params' => ['location' => 'Oradea']],
            ],
            'recommendations' => [
                ['key' => 'publish_robots_sitemap', 'priority' => 'p0', 'window' => '0-7', 'source' => 'check:sitemap_xml', 'params' => []],
                ['key' => 'fix_html_lang_h1', 'priority' => 'p0', 'window' => '0-14', 'source' => 'check:html_lang', 'params' => []],
                ['key' => 'add_local_business_schema', 'priority' => 'p0', 'window' => '0-21', 'source' => 'check:jsonld_local', 'params' => ['location' => 'Oradea']],
                ['key' => 'local_landing_page', 'priority' => 'p1', 'window' => '15-30', 'source' => 'visibility:local_not_recommended', 'params' => ['location' => 'Oradea']],
                ['key' => 'publish_llms_txt', 'priority' => 'p2', 'window' => '60-90', 'source' => 'check:llms_txt', 'params' => []],
            ],
            'narrative' => [
                'verdict' => 'Demo verdict: the site is reachable, but local and technical signals are too weak for strong local AI recommendations.',
                'summary' => ['Demo: robots.txt and sitemap.xml return 404.', 'Demo: recommended in 3 of 11 successful tests.'],
                'h1' => 'Custom software development in Oradea',
                'title' => 'Software development Oradea | Web apps, CRM and AI',
                'meta_description' => 'Custom web apps, CRM and AI integrations for companies in Oradea and Romania.',
                'source' => 'demo',
            ],
        ]);
    }

    public function demo(): static
    {
        return $this->state(fn () => ['is_demo' => true]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => AnalysisStatus::Failed,
            'error_message' => 'All providers failed.',
            'completed_at' => now(),
        ]);
    }

    /**
     * A completed analysis with 3 questions x 4 providers, brand mentions, competitors and scores.
     * Recommendation pattern (per provider): openai 0/3, gemini 1/3, anthropic 0/3, perplexity 2/3,
     * one anthropic response failed => 11 successful tests, 3 recommendations, score 27.27.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => AnalysisStatus::Completed,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ])->afterCreating(function (Analysis $analysis) {
            $recommendedFor = ['gemini' => [1], 'perplexity' => [1, 2]];
            $providers = collect(ProviderSlug::cases())->map(
                fn (ProviderSlug $slug) => AiProvider::firstWhere('slug', $slug->value)
                    ?? AiProvider::factory()->slug($slug)->create()
            );

            $scores = [];
            $totals = ['recommended' => 0, 'mentioned' => 0, 'cited' => 0, 'successful' => 0, 'failed' => 0];

            foreach ([1, 2, 3] as $position) {
                $question = $analysis->questions()->create([
                    'position' => $position,
                    'question' => "Recommend software development companies in Romania (#{$position}).",
                ]);

                foreach ($providers as $provider) {
                    $slug = $provider->slug->value;
                    $scores[$slug] ??= ['recommended' => 0, 'successful' => 0, 'total' => 0, 'percentage' => 0];
                    $scores[$slug]['total']++;

                    if ($slug === 'anthropic' && $position === 3) {
                        $analysis->responses()->create([
                            'analysis_question_id' => $question->id,
                            'ai_provider_id' => $provider->id,
                            'provider' => $slug,
                            'model' => $provider->model,
                            'status' => ResponseStatus::Failed,
                            'attempts' => 1,
                            'error_code' => 'timeout',
                            'error_message' => 'The provider did not respond in time.',
                        ]);
                        $totals['failed']++;

                        continue;
                    }

                    $recommended = in_array($position, $recommendedFor[$slug] ?? [], true);
                    $text = $recommended
                        ? "Here are some options:\n1. **{$analysis->company_name}** — strong custom software team.\n2. **Endava** — large delivery centers.\n3. **Softvision** — product engineering."
                        : "Here are some options:\n1. **Endava** — large delivery centers.\n2. **Softvision** — product engineering.\n3. **Fortech** — outsourcing.";

                    $response = $analysis->responses()->create([
                        'analysis_question_id' => $question->id,
                        'ai_provider_id' => $provider->id,
                        'provider' => $slug,
                        'model' => $provider->model,
                        'status' => ResponseStatus::Succeeded,
                        'is_demo' => $analysis->is_demo,
                        'raw_response' => ['text' => $text],
                        'response_text' => $text,
                        'citations' => $recommended ? [['url' => 'https://'.$analysis->domain, 'title' => $analysis->company_name]] : [],
                        'duration_ms' => 1800,
                        'input_tokens' => 120,
                        'output_tokens' => 400,
                        'search_calls' => 1,
                        'estimated_cost' => 0.0118,
                        'attempts' => 1,
                        'started_at' => now()->subMinute(),
                        'completed_at' => now(),
                    ]);

                    $response->brandMention()->create([
                        'analysis_id' => $analysis->id,
                        'classification' => $recommended ? MentionClassification::Recommended : MentionClassification::NotMentioned,
                        'matched_terms' => $recommended ? [$analysis->company_name] : [],
                        'evidence' => $recommended ? [['type' => 'name', 'term' => $analysis->company_name, 'snippet' => "1. **{$analysis->company_name}** — strong custom software team."]] : [],
                    ]);

                    $scores[$slug]['successful']++;
                    $totals['successful']++;
                    if ($recommended) {
                        $scores[$slug]['recommended']++;
                        $totals['recommended']++;
                        $totals['mentioned']++;
                        $totals['cited']++;
                    }
                }
            }

            foreach ($scores as $slug => $score) {
                $scores[$slug]['percentage'] = $score['successful'] > 0 ? round($score['recommended'] / $score['successful'] * 100, 2) : 0;
            }

            foreach ([['Endava', 11, ['openai', 'gemini', 'anthropic', 'perplexity']], ['Softvision', 11, ['openai', 'gemini', 'anthropic', 'perplexity']], ['Fortech', 8, ['openai', 'gemini', 'anthropic']]] as [$name, $count, $seenBy]) {
                $analysis->competitors()->create([
                    'name' => $name,
                    'normalized_name' => strtolower($name),
                    'mention_count' => $count,
                    'providers' => $seenBy,
                ]);
            }

            $analysis->update([
                'visibility_score' => round($totals['recommended'] / $totals['successful'] * 100, 2),
                'successful_tests' => $totals['successful'],
                'failed_tests' => $totals['failed'],
                'recommendation_count' => $totals['recommended'],
                'mention_count' => $totals['mentioned'],
                'citation_count' => $totals['cited'],
                'provider_scores' => $scores,
                'estimated_cost' => round($totals['successful'] * 0.0118, 6),
                'website_inspection' => ['title' => $analysis->company_name, 'meta_description' => 'Custom software development.', 'h1' => 'We build software', 'services' => ['Custom software', 'ERP systems'], 'fetched' => true],
            ]);
        });
    }
}
