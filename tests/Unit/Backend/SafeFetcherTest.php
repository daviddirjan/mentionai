<?php

namespace Tests\Unit\Backend;

use App\Services\Inspection\SafeFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SafeFetcherTest extends TestCase
{
    private function fetcher(array $map = []): SafeFetcher
    {
        return new SafeFetcher(fn (string $host) => $map[$host] ?? ['93.184.216.34']);
    }

    public function test_private_resolution_is_blocked_without_a_request(): void
    {
        Http::fake();

        $result = $this->fetcher(['evil.example' => ['127.0.0.1']])->get('https://evil.example/robots.txt');

        $this->assertFalse($result->ok());
        $this->assertSame('unsafe_url', $result->error);
        $this->assertNull($result->status);
        Http::assertNothingSent();
    }

    public function test_redirect_to_a_private_host_is_blocked(): void
    {
        Http::fake([
            'public.example/*' => Http::response('', 302, ['Location' => 'http://internal.example/']),
            '*' => Http::response('secret', 200),
        ]);

        $result = $this->fetcher(['internal.example' => ['10.0.0.8']])->get('https://public.example/llms.txt');

        $this->assertSame('unsafe_url', $result->error);
        Http::assertSentCount(1);
    }

    public function test_redirect_to_metadata_ip_is_blocked(): void
    {
        Http::fake(['*' => Http::response('', 301, ['Location' => 'http://169.254.169.254/latest/'])]);

        $this->assertSame('unsafe_url', $this->fetcher()->get('https://public.example/')->error);
        Http::assertSentCount(1);
    }

    public function test_too_large_body_is_rejected(): void
    {
        config(['mentionai.website_inspection.max_bytes' => 500]);
        Http::fake(['*' => Http::response(str_repeat('a', 5000), 200, ['Content-Type' => 'text/plain'])]);

        $result = $this->fetcher()->get('https://big.example/sitemap.xml');

        $this->assertSame('too_large', $result->error);
        $this->assertNull($result->body);
    }

    public function test_per_call_byte_cap_overrides_the_default(): void
    {
        Http::fake(['*' => Http::response(str_repeat('a', 300), 200, ['Content-Type' => 'text/plain'])]);

        $this->assertSame('too_large', $this->fetcher()->get('https://a.example/x', ['max_bytes' => 100])->error);
        $this->assertTrue($this->fetcher()->get('https://a.example/x', ['max_bytes' => 1000])->ok());
    }

    public function test_text_files_are_accepted_when_no_content_type_filter_is_given(): void
    {
        Http::fake(['*' => Http::response("User-agent: *\nDisallow:", 200, ['Content-Type' => 'text/plain; charset=utf-8'])]);

        $result = $this->fetcher()->get('https://acme.example/robots.txt');

        $this->assertTrue($result->ok());
        $this->assertSame(200, $result->status);
        $this->assertSame('text/plain', $result->contentType());
        $this->assertStringContainsString('User-agent', $result->body);
    }

    public function test_content_type_filter_rejects_other_types(): void
    {
        Http::fake(['*' => Http::response('%PDF', 200, ['Content-Type' => 'application/pdf'])]);

        $this->assertSame('content_type', $this->fetcher()->get('https://acme.example/', ['content_types' => ['text/html']])->error);
    }

    public function test_http_errors_keep_the_status_and_never_throw(): void
    {
        Http::fake(['*' => Http::response('missing', 404)]);
        $result = $this->fetcher()->get('https://acme.example/llms.txt');
        $this->assertSame(404, $result->status);
        $this->assertSame('http_404', $result->error);

        Http::fake(['*' => fn () => throw new ConnectionException('timed out')]);
        $this->assertSame('fetch_failed', $this->fetcher()->get('https://acme.example/')->error);
    }

    public function test_redirects_are_counted_and_the_final_url_reported(): void
    {
        Http::fake(fn ($request) => parse_url($request->url(), PHP_URL_HOST) === 'acme.example'
            ? Http::response('', 301, ['Location' => 'https://www.acme.example/'])
            : Http::response('<html></html>', 200, ['Content-Type' => 'text/html']));

        $result = $this->fetcher()->get('https://acme.example/');

        $this->assertTrue($result->ok());
        $this->assertSame(1, $result->redirects);
        $this->assertSame('https://www.acme.example/', $result->finalUrl);
    }

    public function test_same_host_option_refuses_redirects_to_another_host(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://cdn.other.example/robots.txt'])]);

        $result = $this->fetcher()->get('https://acme.example/robots.txt', ['same_host' => 'acme.example']);

        $this->assertSame('off_host', $result->error);
        Http::assertSentCount(1);
    }

    public function test_expired_shared_deadline_times_out_without_a_request(): void
    {
        Http::fake();

        $this->assertSame('timeout', $this->fetcher()->get('https://acme.example/', ['deadline' => microtime(true) - 5])->error);
        Http::assertNothingSent();
    }
}
