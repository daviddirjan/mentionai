<?php

namespace Tests\Feature\Frontend;

use App\Http\Support\SafeMarkdown;

class SeoAndLegalTest extends FrontendTestCase
{
    public function test_sitemap_lists_every_locale_and_page_with_alternates(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        foreach (config('mentionai.locales') as $locale) {
            foreach (['', '/contact', '/privacy-policy', '/terms-of-service', '/cookie-policy'] as $path) {
                $response->assertSee('<loc>'.url("/{$locale}{$path}").'</loc>', false);
            }
        }
        $response->assertSee('hreflang="x-default"', false);
        $response->assertDontSee('/analysis/', false);
    }

    public function test_robots_txt_points_to_sitemap_and_blocks_analysis_pages(): void
    {
        $response = $this->get('/robots.txt')->assertOk();

        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
        $response->assertSee('Sitemap: '.url('/sitemap.xml'), false);
        $response->assertSee('Disallow: /en/analysis/', false);
        $response->assertSee('Disallow: /admin', false);
    }

    public function test_landing_has_seo_metadata_and_structured_data(): void
    {
        $this->get('/en')
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card"', false)
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('"@type":"FAQPage"', false);
    }

    public function test_landing_contains_all_sections_without_fabricated_proof(): void
    {
        $response = $this->get('/en')->assertOk();

        foreach (['id="analyze"', 'id="platforms"', 'id="compare"', 'id="how"', 'id="example"', 'id="pricing"', 'id="faq"', 'id="contact"'] as $anchor) {
            $response->assertSee($anchor, false);
        }
        $response->assertSeeInOrder(['id="analyze"', 'id="platforms"', 'id="compare"', 'id="how"', 'id="example"', 'id="pricing"', 'id="faq"', 'id="contact"'], false);
        $response->assertDontSee('id="why"', false)->assertDontSee('id="features"', false);
        $response->assertSee('Illustrative example with sample data');
        $response->assertDontSee('testimonial', false);
    }

    public function test_legal_pages_render_with_placeholder_notice(): void
    {
        foreach (['privacy-policy' => 'Privacy Policy', 'terms-of-service' => 'Terms of Service', 'cookie-policy' => 'Cookie Policy'] as $path => $title) {
            $this->get("/en/{$path}")
                ->assertOk()
                ->assertSee($title)
                ->assertSee('must be replaced before production launch');
        }

        $this->get('/en/privacy-policy')->assertSee('subject=data_deletion', false);
    }

    public function test_data_processing_disclosure_is_near_the_form(): void
    {
        $this->get('/en')->assertSee('OpenAI, Google, Anthropic, Perplexity');
    }

    public function test_unknown_page_renders_branded_404(): void
    {
        $this->get('/en/does-not-exist')->assertNotFound()->assertSee('Page not found');
        $this->get('/nope')->assertNotFound();
    }

    public function test_safe_markdown_escapes_html_and_renders_limited_markup(): void
    {
        $html = SafeMarkdown::render("<img src=x onerror=alert(1)>\n\n1. **One**\n2. [Site](https://example.com/a?b=1&c=2)\n\n- a\n- b");

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringContainsString('<ol><li><strong>One</strong></li>', $html);
        $this->assertStringContainsString('<a href="https://example.com/a?b=1&amp;c=2"', $html);
        $this->assertStringContainsString('<ul><li>a</li><li>b</li></ul>', $html);
        $this->assertSame('', SafeMarkdown::render(null));
        $this->assertStringNotContainsString('<a ', SafeMarkdown::render('[x](javascript:alert(1))'));
    }
}
