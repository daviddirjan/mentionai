<?php

namespace App\Services\Audit;

use App\Contracts\RecommendationBuilder;
use App\Data\Recommendation;
use App\Data\SiteCheck;
use App\Enums\CheckStatus;
use App\Enums\Priority;
use App\Enums\QuestionCategory;
use App\Models\Analysis;

/** The trigger table in docs/AUDIT.md, applied to the stored site audit and visibility results. */
class RuleRecommendationBuilder implements RecommendationBuilder
{
    public function build(Analysis $analysis): array
    {
        $checks = [];
        foreach ($analysis->siteChecks() as $check) {
            $checks[$check->key] = $check;
        }
        $facts = AuditFacts::for($analysis);
        $location = filled($analysis->location) ? (string) $analysis->location : null;

        $failed = fn (string ...$keys): ?string => $this->first($checks, $keys, fn (SiteCheck $c) => $c->status === CheckStatus::Fail);
        $notPassed = fn (string ...$keys): ?string => $this->first($checks, $keys, fn (SiteCheck $c) => $c->status !== CheckStatus::Pass);

        $recs = [];
        $add = function (string $key, Priority $priority, string $window, ?string $source, array $params = []) use (&$recs): void {
            if ($source !== null && ! isset($recs[$key])) {
                $recs[$key] = new Recommendation($key, $priority, $window, $source, $params);
            }
        };

        $add('fix_homepage_status', Priority::P0, '0-7', $failed('homepage_status'));
        $add('fix_https', Priority::P0, '0-7', $failed('https'));
        $add('publish_robots_sitemap', Priority::P0, '0-7', $failed('robots_txt', 'sitemap_xml'));

        $blocked = array_values(array_filter($checks, fn (SiteCheck $c) => str_starts_with($c->key, 'ai_crawler_') && $c->status === CheckStatus::Fail));
        if ($blocked !== []) {
            $add('allow_ai_crawlers', Priority::P0, '0-7', 'check:'.$blocked[0]->key, ['bots' => implode(', ', array_map(fn (SiteCheck $c) => (string) ($c->params['bot'] ?? $c->key), $blocked))]);
        }

        $add('fix_robots_meta', Priority::P0, '0-7', $failed('robots_meta'));
        $add('fix_html_lang_h1', Priority::P0, '0-14', $notPassed('html_lang', 'h1_count'));
        $add('fix_title_meta', Priority::P0, '0-14', $notPassed('title_tag', 'meta_description'));
        $add('add_canonical', Priority::P1, '15-30', $notPassed('canonical'));
        $add('add_local_business_schema', Priority::P0, '0-21', $notPassed('jsonld_present', 'jsonld_local', 'jsonld_locality_match'), ['location' => $location]);
        $add('add_hreflang', Priority::P1, '15-30', $notPassed('hreflang'));
        $add('add_open_graph', Priority::P2, '60-90', $notPassed('open_graph'));
        $add('publish_llms_txt', Priority::P2, '60-90', $notPassed('llms_txt'));

        if ($location !== null && $facts->unrecommendedQuestions(QuestionCategory::Local->value)->isNotEmpty()) {
            $add('local_landing_page', Priority::P1, '15-30', 'visibility:local_not_recommended', ['location' => $location]);
        }

        $gap = array_slice($facts->competitorGap(0.25), 0, 3);
        if ($gap !== []) {
            $add('competitor_gap', Priority::P1, '30-60', 'visibility:competitor_gap', ['competitors' => implode(', ', array_column($gap, 'name'))]);
        }

        $providers = $facts->providerGaps(2);
        if ($providers !== []) {
            $add('provider_gap', Priority::P1, '30-60', 'visibility:provider_gap', ['provider' => implode(', ', array_map($facts->providerName(...), $providers))]);
        }

        $score = $facts->score();
        if ($score !== null && $score < 50) {
            $add('case_studies', Priority::P1, '30-60', 'visibility:score_below_50');
        }

        if ($facts->successful->isNotEmpty() && ! $facts->brandCited()) {
            $add('earn_citations', Priority::P2, '60-90', 'visibility:no_brand_citation');
        }

        $add('third_party_profiles', Priority::P2, '60-90', 'always');

        return $this->sorted(array_values($recs));
    }

    /**
     * P0 → P2, then by window start and end; ties keep rule order (usort is stable), so third_party_profiles stays last.
     *
     * @param  list<Recommendation>  $recs
     * @return list<Recommendation>
     */
    private function sorted(array $recs): array
    {
        $rank = fn (Recommendation $r): array => [$r->priority->planPhase(), ...array_map('intval', explode('-', $r->window) + [1 => 0])];
        usort($recs, fn (Recommendation $a, Recommendation $b) => $rank($a) <=> $rank($b));

        return $recs;
    }

    /**
     * @param  array<string, SiteCheck>  $checks
     * @param  list<string>  $keys
     */
    private function first(array $checks, array $keys, callable $test): ?string
    {
        foreach ($keys as $key) {
            if (isset($checks[$key]) && $test($checks[$key])) {
                return 'check:'.$key;
            }
        }

        return null;
    }
}
