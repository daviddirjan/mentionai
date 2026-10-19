<?php

namespace Tests\Feature\Backend;

use App\Contracts\SiteAuditor;
use App\Data\SiteCheck;
use App\Enums\CheckGroup;
use App\Enums\CheckStatus;
use App\Models\Analysis;
use App\Services\Inspection\SafeFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SiteAuditorTest extends TestCase
{
    use RefreshDatabase;

    private const GOOD_HTML = <<<'HTML'
<!doctype html><html lang="ro"><head>
<title>Dezvoltare software Oradea | Aplicații web</title>
<meta name="description" content="Aplicații web, CRM și integrări AI pentru companii din Oradea și din toată România, livrate la timp.">
<link rel="canonical" href="https://acme.example/">
<link rel="alternate" hreflang="ro" href="https://acme.example/"><link rel="alternate" hreflang="en" href="https://acme.example/en/">
<meta property="og:title" content="Acme"><meta property="og:description" content="Software"><meta property="og:image" content="https://acme.example/og.png">
<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"Organization","name":"Acme"},{"@type":"ProfessionalService","name":"Acme","address":{"@type":"PostalAddress","streetAddress":"Str. Republicii 1","addressLocality":"Oradea","addressCountry":"RO"}}]}</script>
</head><body><h1>Dezvoltare software în Oradea</h1></body></html>
HTML;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(SafeFetcher::class, fn () => new SafeFetcher(fn () => ['93.184.216.34']));
    }

    /** @param  array<string, array{0: string, 1?: int, 2?: array<string, string>}>  $paths */
    private function site(array $paths): void
    {
        Http::fake(function (Request $request) use ($paths) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '/';
            if (! isset($paths[$path])) {
                return Http::response('<html><body>Not found</body></html>', 404, ['Content-Type' => 'text/html']);
            }
            [$body, $status, $headers] = $paths[$path] + [1 => 200, 2 => []];

            return Http::response($body, $status, $headers + ['Content-Type' => str_ends_with($path, '/') ? 'text/html; charset=utf-8' : 'text/plain']);
        });
    }

    private function audit(array $attributes = []): array
    {
        $analysis = Analysis::factory()->create($attributes + ['website_url' => 'https://acme.example/', 'domain' => 'acme.example', 'language' => 'ro']);
        $checks = app(SiteAuditor::class)->audit($analysis);

        $this->assertContainsOnlyInstancesOf(SiteCheck::class, $checks);
        $keyed = [];
        foreach ($checks as $check) {
            $keyed[$check->key] = $check;
        }

        return $keyed;
    }

    public function test_emits_exactly_the_contract_keys_and_groups(): void
    {
        $this->site(['/' => [self::GOOD_HTML]]);

        $checks = $this->audit(['location' => 'Oradea']);

        $this->assertSame([
            'homepage_status', 'https', 'robots_txt', 'sitemap_xml', 'llms_txt', 'response_time',
            'ai_crawler_gptbot', 'ai_crawler_oai_searchbot', 'ai_crawler_claudebot', 'ai_crawler_perplexitybot', 'ai_crawler_google_extended',
            'title_tag', 'meta_description', 'h1_count', 'html_lang', 'canonical', 'robots_meta', 'hreflang', 'open_graph',
            'jsonld_present', 'jsonld_local', 'jsonld_locality_match',
        ], array_keys($checks));
        $this->assertSame(CheckGroup::Access, $checks['llms_txt']->group);
        $this->assertSame(CheckGroup::AiCrawlers, $checks['ai_crawler_claudebot']->group);
        $this->assertSame(CheckGroup::Structure, $checks['open_graph']->group);
        $this->assertSame(CheckGroup::Entity, $checks['jsonld_locality_match']->group);
        $this->assertSame(CheckStatus::Info, $checks['response_time']->status);
        $this->assertSame('ClaudeBot', $checks['ai_crawler_claudebot']->params['bot']);
        $this->assertSame('Google-Extended', $checks['ai_crawler_google_extended']->params['bot']);
    }

    public function test_locality_check_only_exists_with_a_location(): void
    {
        $this->site(['/' => [self::GOOD_HTML]]);

        $this->assertArrayNotHasKey('jsonld_locality_match', $this->audit(['location' => null]));
    }

    public function test_missing_robots_sitemap_and_llms(): void
    {
        $this->site(['/' => [self::GOOD_HTML]]);

        $checks = $this->audit();

        $this->assertSame(CheckStatus::Pass, $checks['homepage_status']->status);
        $this->assertSame('GET / → 200', $checks['homepage_status']->evidence);
        $this->assertSame(CheckStatus::Fail, $checks['robots_txt']->status);
        $this->assertSame('GET /robots.txt → 404', $checks['robots_txt']->evidence);
        $this->assertSame(CheckStatus::Fail, $checks['sitemap_xml']->status);
        $this->assertSame('GET /sitemap.xml → 404', $checks['sitemap_xml']->evidence);
        $this->assertSame(CheckStatus::Warn, $checks['llms_txt']->status);
        $this->assertSame('GET /llms.txt → 404', $checks['llms_txt']->evidence);
        // No robots.txt means no crawler is blocked.
        $this->assertSame(CheckStatus::Pass, $checks['ai_crawler_gptbot']->status);
    }

    public function test_present_robots_sitemap_and_llms_pass(): void
    {
        $this->site([
            '/' => [self::GOOD_HTML],
            '/robots.txt' => ["User-agent: *\nDisallow: /admin\nSitemap: https://acme.example/sitemap.xml"],
            '/sitemap.xml' => ['<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://acme.example/</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']],
            '/llms.txt' => ["# Acme\n> Software house in Oradea"],
        ]);

        $checks = $this->audit(['location' => 'Oradea']);

        foreach (['homepage_status', 'https', 'robots_txt', 'sitemap_xml', 'llms_txt', 'title_tag', 'meta_description', 'h1_count', 'html_lang', 'canonical', 'robots_meta', 'hreflang', 'open_graph', 'jsonld_present', 'jsonld_local', 'jsonld_locality_match'] as $key) {
            $this->assertSame(CheckStatus::Pass, $checks[$key]->status, "{$key}: {$checks[$key]->evidence}");
        }
        foreach (array_filter($checks, fn ($c) => $c->group === CheckGroup::AiCrawlers) as $check) {
            $this->assertSame(CheckStatus::Pass, $check->status);
        }
        $this->assertStringContainsString('declared in robots.txt', $checks['sitemap_xml']->evidence);
        $this->assertSame('<html lang="ro">', $checks['html_lang']->evidence);
        $this->assertSame('addressLocality "Oradea" matches "Oradea"', $checks['jsonld_locality_match']->evidence);
        $this->assertStringContainsString('ProfessionalService', $checks['jsonld_present']->params['types']);
    }

    public function test_sitemap_declared_in_robots_but_missing_fails(): void
    {
        $this->site([
            '/' => [self::GOOD_HTML],
            '/robots.txt' => ["User-agent: *\nAllow: /\nSitemap: https://acme.example/sitemap_index.xml"],
            '/sitemap.xml' => ['<urlset></urlset>', 200, ['Content-Type' => 'application/xml']],
        ]);

        $check = $this->audit()['sitemap_xml'];

        $this->assertSame(CheckStatus::Fail, $check->status);
        $this->assertSame('Declared in robots.txt, GET /sitemap_index.xml → 404', $check->evidence);
    }

    public function test_sitemap_on_another_host_is_never_fetched(): void
    {
        $this->site([
            '/' => [self::GOOD_HTML],
            '/robots.txt' => ["User-agent: *\nSitemap: https://evil.example/sitemap.xml"],
        ]);

        $check = $this->audit()['sitemap_xml'];

        $this->assertSame(CheckStatus::Fail, $check->status);
        $this->assertStringContainsString('another host', $check->evidence);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'evil.example'));
    }

    public function test_gptbot_disallowed_fails_only_that_bot(): void
    {
        $this->site([
            '/' => [self::GOOD_HTML],
            '/robots.txt' => ["User-agent: GPTBot\nDisallow: /\n\nUser-agent: *\nAllow: /"],
        ]);

        $checks = $this->audit();

        $this->assertSame(CheckStatus::Fail, $checks['ai_crawler_gptbot']->status);
        $this->assertSame('robots.txt User-agent: GPTBot → Disallow: /', $checks['ai_crawler_gptbot']->evidence);
        $this->assertSame(CheckStatus::Pass, $checks['ai_crawler_oai_searchbot']->status);
        $this->assertSame(CheckStatus::Pass, $checks['ai_crawler_claudebot']->status);
    }

    public function test_wildcard_disallow_blocks_every_bot_without_its_own_group(): void
    {
        $this->site([
            '/' => [self::GOOD_HTML],
            '/robots.txt' => ["User-agent: *\nDisallow: /\n\nUser-agent: ClaudeBot\nAllow: /"],
        ]);

        $checks = $this->audit();

        $this->assertSame(CheckStatus::Pass, $checks['ai_crawler_claudebot']->status);
        $this->assertSame(CheckStatus::Fail, $checks['ai_crawler_perplexitybot']->status);
        $this->assertSame('robots.txt User-agent: * → Disallow: /', $checks['ai_crawler_perplexitybot']->evidence);
    }

    public function test_robots_served_as_html_page_is_not_a_robots_file(): void
    {
        $this->site([
            '/' => [self::GOOD_HTML],
            '/robots.txt' => ['<!doctype html><html><body>Home</body></html>', 200, ['Content-Type' => 'text/html']],
        ]);

        $this->assertSame(CheckStatus::Fail, $this->audit()['robots_txt']->status);
    }

    public function test_two_h1_missing_lang_and_weak_structure(): void
    {
        $this->site(['/' => ['<html><head><title>Acme</title><meta name="robots" content="noindex, nofollow"></head><body><h1>One</h1><h1>Two</h1></body></html>']]);

        $checks = $this->audit();

        $this->assertSame(CheckStatus::Warn, $checks['h1_count']->status);
        $this->assertSame('2 × <h1>', $checks['h1_count']->evidence);
        $this->assertSame(2, $checks['h1_count']->params['count']);
        $this->assertSame(CheckStatus::Fail, $checks['html_lang']->status);
        $this->assertSame('<html> without lang', $checks['html_lang']->evidence);
        $this->assertSame(CheckStatus::Warn, $checks['title_tag']->status);
        $this->assertSame(4, $checks['title_tag']->params['length']);
        $this->assertSame(CheckStatus::Fail, $checks['meta_description']->status);
        $this->assertSame(CheckStatus::Fail, $checks['robots_meta']->status);
        $this->assertSame(CheckStatus::Warn, $checks['canonical']->status);
        $this->assertSame(CheckStatus::Warn, $checks['hreflang']->status);
        $this->assertSame(CheckStatus::Fail, $checks['open_graph']->status);
        $this->assertSame(CheckStatus::Fail, $checks['jsonld_present']->status);
        $this->assertSame(CheckStatus::Fail, $checks['jsonld_local']->status);
    }

    public function test_language_mismatch_is_a_warning(): void
    {
        $this->site(['/' => [str_replace('lang="ro"', 'lang="en-US"', self::GOOD_HTML)]]);

        $check = $this->audit()['html_lang'];

        $this->assertSame(CheckStatus::Warn, $check->status);
        $this->assertSame('en-US', $check->params['lang']);
    }

    public function test_local_business_with_matching_locality_passes_and_other_city_warns(): void
    {
        $this->site(['/' => [self::GOOD_HTML]]);
        $checks = $this->audit(['location' => 'oradea, Bihor']);
        $this->assertSame(CheckStatus::Pass, $checks['jsonld_local']->status);
        $this->assertSame(CheckStatus::Pass, $checks['jsonld_locality_match']->status);
        $this->assertSame('oradea, Bihor', $checks['jsonld_locality_match']->params['location']);

        $checks = $this->audit(['location' => 'Cluj-Napoca']);
        $this->assertSame(CheckStatus::Warn, $checks['jsonld_locality_match']->status);
    }

    public function test_organization_without_address_is_a_local_warning(): void
    {
        $this->site(['/' => ['<html lang="ro"><head><script type="application/ld+json">{"@type":"Organization","name":"Acme"}</script></head><body><h1>x</h1></body></html>']]);

        $checks = $this->audit(['location' => 'Oradea']);

        $this->assertSame(CheckStatus::Pass, $checks['jsonld_present']->status);
        $this->assertSame(CheckStatus::Warn, $checks['jsonld_local']->status);
        $this->assertSame(CheckStatus::Fail, $checks['jsonld_locality_match']->status);
    }

    public function test_http_only_site_fails_https(): void
    {
        $this->site(['/' => [self::GOOD_HTML]]);

        $check = $this->audit(['website_url' => 'http://acme.example/'])['https'];

        $this->assertSame(CheckStatus::Fail, $check->status);
        $this->assertSame('Final URL http://acme.example/', $check->evidence);
    }

    public function test_unreachable_homepage_never_throws(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);

        $checks = $this->audit(['location' => 'Oradea']);

        $this->assertSame(CheckStatus::Fail, $checks['homepage_status']->status);
        $this->assertSame('GET / → 503', $checks['homepage_status']->evidence);
        $this->assertSame(CheckStatus::Warn, $checks['h1_count']->status);
        $this->assertSame(CheckStatus::Warn, $checks['ai_crawler_gptbot']->status);
        $this->assertArrayHasKey('jsonld_locality_match', $checks);
    }

    public function test_private_host_is_never_requested(): void
    {
        $this->app->bind(SafeFetcher::class, fn () => new SafeFetcher(fn () => ['10.0.0.1']));
        Http::fake();

        $checks = $this->audit();

        $this->assertSame('GET / → unsafe_url', $checks['homepage_status']->evidence);
        $this->assertSame(CheckStatus::Fail, $checks['robots_txt']->status);
        Http::assertNothingSent();
    }

    public function test_checks_round_trip_through_the_model(): void
    {
        $this->site(['/' => [self::GOOD_HTML]]);
        $analysis = Analysis::factory()->create(['website_url' => 'https://acme.example/', 'domain' => 'acme.example']);

        $analysis->update(['site_audit' => array_map(fn (SiteCheck $c) => $c->toArray(), app(SiteAuditor::class)->audit($analysis))]);

        $this->assertCount(21, $analysis->refresh()->siteChecks());
    }
}
