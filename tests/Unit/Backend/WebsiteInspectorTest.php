<?php

namespace Tests\Unit\Backend;

use App\Services\Inspection\IpGuard;
use App\Services\Inspection\WebsiteInspector;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebsiteInspectorTest extends TestCase
{
    private function inspector(array $map = []): WebsiteInspector
    {
        return new WebsiteInspector(fn (string $host) => $map[$host] ?? ['93.184.216.34']);
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function ips(): array
    {
        return [
            'public v4' => ['93.184.216.34', true],
            'public v6' => ['2606:2800:220:1:248:1893:25c8:1946', true],
            'loopback' => ['127.0.0.1', false],
            'private 10' => ['10.1.2.3', false],
            'private 172' => ['172.16.0.9', false],
            'private 192' => ['192.168.1.1', false],
            'metadata' => ['169.254.169.254', false],
            'zero' => ['0.0.0.0', false],
            'cgnat' => ['100.64.0.1', false],
            'v6 loopback' => ['::1', false],
            'v6 unique local' => ['fd00::1', false],
            'v6 link local' => ['fe80::1', false],
            'v4 mapped loopback' => ['::ffff:127.0.0.1', false],
            'v4 mapped metadata' => ['::ffff:169.254.169.254', false],
            '6to4' => ['2002:7f00:1::', false],
            'garbage' => ['not-an-ip', false],
        ];
    }

    #[DataProvider('ips')]
    public function test_ip_guard(string $ip, bool $public): void
    {
        $this->assertSame($public, IpGuard::isPublic($ip));
    }

    public function test_extracts_identity_hints(): void
    {
        $html = <<<'HTML'
<html><head><title> Acme   Software </title>
<meta name="description" content="Custom ERP for factories.">
<meta property="og:title" content="Acme OG"><meta property="og:site_name" content="Acme">
<script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":"Acme Software","description":"We build ERP","address":{"@type":"PostalAddress","addressLocality":"Timisoara"}}</script>
</head><body><nav><a href="/erp">ERP Development</a><a href="/contact">Contact</a></nav>
<h1>We build software</h1><h2>Web Development</h2><h3>Mobile Apps</h3></body></html>
HTML;
        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html; charset=utf-8'])]);

        $result = $this->inspector()->inspect('https://acme.example');

        $this->assertTrue($result['fetched']);
        $this->assertSame('Acme Software', $result['title']);
        $this->assertSame('Custom ERP for factories.', $result['meta_description']);
        $this->assertSame('We build software', $result['h1']);
        $this->assertSame('Acme OG', $result['og']['title']);
        $this->assertSame('Organization', $result['json_ld'][0]['type']);
        $this->assertSame('Timisoara', $result['city']);
        $this->assertContains('Web Development', $result['services']);
        $this->assertContains('ERP Development', $result['services']);
        $this->assertNotContains('Contact', $result['services']);
    }

    public function test_private_resolution_is_blocked_without_any_request(): void
    {
        Http::fake();
        $result = $this->inspector(['evil.example' => ['10.0.0.5']])->inspect('https://evil.example');

        $this->assertFalse($result['fetched']);
        $this->assertSame('unsafe_url', $result['error']);
        Http::assertNothingSent();
    }

    public function test_any_private_address_among_several_blocks(): void
    {
        Http::fake();
        $result = $this->inspector(['mixed.example' => ['93.184.216.34', '127.0.0.1']])->inspect('https://mixed.example');

        $this->assertSame('unsafe_url', $result['error']);
        Http::assertNothingSent();
    }

    public function test_ip_literals_ports_and_schemes_are_rejected(): void
    {
        Http::fake();
        foreach (['http://127.0.0.1/', 'http://[::1]/', 'https://acme.example:8080/', 'ftp://acme.example/', 'file:///etc/passwd', 'https://user:pw@acme.example/'] as $url) {
            $this->assertSame('unsafe_url', $this->inspector()->inspect($url)['error'], $url);
        }
        Http::assertNothingSent();
    }

    public function test_redirect_to_private_host_is_blocked_on_the_second_hop(): void
    {
        Http::fake([
            'public.example/*' => Http::response('', 302, ['Location' => 'http://internal.example/admin']),
            '*' => Http::response('<html><title>secret</title></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = $this->inspector(['internal.example' => ['192.168.0.10']])->inspect('https://public.example/');

        $this->assertFalse($result['fetched']);
        $this->assertSame('unsafe_url', $result['error']);
        Http::assertSentCount(1);
    }

    public function test_redirect_to_metadata_ip_literal_is_blocked(): void
    {
        Http::fake(['*' => Http::response('', 301, ['Location' => 'http://169.254.169.254/latest/meta-data/'])]);

        $this->assertSame('unsafe_url', $this->inspector()->inspect('https://public.example/')['error']);
        Http::assertSentCount(1);
    }

    public function test_safe_redirects_are_followed_with_relative_locations(): void
    {
        Http::fake([
            'public.example/old' => Http::response('', 301, ['Location' => '/new']),
            'public.example/new' => Http::response('<html><title>New</title></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = $this->inspector()->inspect('https://public.example/old');

        $this->assertTrue($result['fetched']);
        $this->assertSame('New', $result['title']);
        $this->assertSame('https://public.example/new', $result['final_url']);
    }

    public function test_redirect_loops_stop_at_the_configured_limit(): void
    {
        config(['mentionai.website_inspection.max_redirects' => 2]);
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://public.example/again'])]);

        $result = $this->inspector()->inspect('https://public.example/');

        $this->assertSame('too_many_redirects', $result['error']);
        Http::assertSentCount(3);
    }

    public function test_oversized_body_is_rejected(): void
    {
        config(['mentionai.website_inspection.max_bytes' => 1000]);
        Http::fake(['*' => Http::response('<html><title>x</title>'.str_repeat('a', 5000).'</html>', 200, ['Content-Type' => 'text/html'])]);

        $result = $this->inspector()->inspect('https://big.example');

        $this->assertFalse($result['fetched']);
        $this->assertSame('too_large', $result['error']);
    }

    public function test_non_html_content_is_rejected(): void
    {
        Http::fake(['*' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf'])]);

        $this->assertSame('not_html', $this->inspector()->inspect('https://doc.example')['error']);
    }

    public function test_http_errors_and_exceptions_never_throw(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);
        $this->assertSame('http_500', $this->inspector()->inspect('https://down.example')['error']);

        Http::fake(['*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);
        $result = $this->inspector()->inspect('https://slow.example');
        $this->assertFalse($result['fetched']);
    }

    public function test_unresolvable_host_fails_safely(): void
    {
        Http::fake();
        $result = (new WebsiteInspector(fn () => []))->inspect('https://nowhere.example');

        $this->assertFalse($result['fetched']);
        Http::assertNothingSent();
    }
}
