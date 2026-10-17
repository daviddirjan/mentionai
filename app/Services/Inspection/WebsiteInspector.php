<?php

namespace App\Services\Inspection;

use Closure;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fetches a homepage defensively (SSRF-safe) and extracts identity hints.
 * inspect() never throws: failures come back as ['fetched' => false, 'error' => ...].
 */
class WebsiteInspector
{
    /** @var Closure(string): list<string> */
    private Closure $resolver;

    /** @param  (Closure(string): list<string>)|null  $resolver host => list of IP addresses; injectable for tests */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? fn (string $host): array => $this->resolveDns($host);
    }

    /** @return array<string, mixed> */
    public function inspect(string $url): array
    {
        $config = config('mentionai.website_inspection');
        $deadline = microtime(true) + (float) $config['timeout_seconds'];
        $current = $url;
        $maxBytes = (int) $config['max_bytes'];
        $tooLarge = false;

        try {
            for ($hop = 0; $hop <= (int) $config['max_redirects']; $hop++) {
                $target = $this->validate($current);
                $remaining = max(1.0, $deadline - microtime(true));
                if ($remaining <= 1.0 && microtime(true) >= $deadline) {
                    return $this->failure($url, 'timeout');
                }

                $response = Http::withHeaders(['User-Agent' => $config['user_agent'], 'Accept' => 'text/html,application/xhtml+xml'])
                    ->timeout((int) ceil($remaining))
                    ->withOptions([
                        'allow_redirects' => false,
                        // Abort mid-transfer once the cap is passed; Content-Length may be absent or wrong.
                        'progress' => function ($downloadTotal, $downloaded) use ($maxBytes, &$tooLarge): void {
                            if ($downloaded > $maxBytes) {
                                $tooLarge = true;
                                throw new \RuntimeException('too_large');
                            }
                        },
                        'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:".(str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip'])]],
                    ])
                    ->get($current);

                $status = $response->status();
                if (in_array($status, [301, 302, 303, 307, 308], true)) {
                    $location = $response->header('Location');
                    if ($location === '') {
                        return $this->failure($url, 'redirect_without_location');
                    }
                    $current = $this->absoluteUrl($current, $location);

                    continue;
                }

                if ($status < 200 || $status >= 300) {
                    return $this->failure($url, "http_{$status}");
                }

                $contentType = strtolower($response->header('Content-Type'));
                if ($contentType !== '' && ! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/xhtml')) {
                    return $this->failure($url, 'not_html');
                }

                $body = $response->body();
                if ((int) $response->header('Content-Length') > $maxBytes || strlen($body) > $maxBytes) {
                    return $this->failure($url, 'too_large');
                }

                return ['fetched' => true, 'error' => null, 'url' => $url, 'final_url' => $current] + $this->extract($body);
            }

            return $this->failure($url, 'too_many_redirects');
        } catch (UnsafeUrl $e) {
            return $this->failure($url, 'unsafe_url');
        } catch (Throwable $e) {
            return $this->failure($url, $tooLarge ? 'too_large' : 'fetch_failed');
        }
    }

    /**
     * @return array{host: string, port: int, ip: string}
     *
     * @throws UnsafeUrl
     */
    private function validate(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(rtrim($parts['host'] ?? '', '.'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrl;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443], true)) {
            throw new UnsafeUrl;
        }

        $ips = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) ? [trim($host, '[]')] : ($this->resolver)($host);
        if ($ips === []) {
            throw new UnsafeUrl;
        }
        foreach ($ips as $ip) {
            if (! IpGuard::isPublic($ip)) {
                throw new UnsafeUrl;
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    /** @return list<string> */
    private function resolveDns(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($ips));
    }

    private function absoluteUrl(string $base, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/');

        return $origin.$dir.$location;
    }


    /** @return array<string, mixed> */
    private function failure(string $url, string $error): array
    {
        return ['fetched' => false, 'error' => $error, 'url' => $url];
    }

    /** @return array<string, mixed> */
    private function extract(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);

        $text = fn (string $query): ?string => ($node = $xpath->query($query)->item(0)) ? $this->clean($node->textContent) : null;
        $meta = fn (string $attr, string $name): ?string => ($node = $xpath->query("//meta[translate(@{$attr},'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='{$name}']/@content")->item(0)) ? $this->clean($node->nodeValue) : null;

        $jsonLd = $this->jsonLd($xpath);
        $city = null;
        foreach ($jsonLd as $entry) {
            $city ??= $entry['locality'] ?? null;
        }
        $city ??= $meta('name', 'geo.placename');

        $description = $meta('name', 'description') ?? $meta('property', 'og:description');

        return [
            'title' => $text('//title'),
            'meta_description' => $description,
            'h1' => $text('//h1'),
            'og' => array_filter([
                'title' => $meta('property', 'og:title'),
                'description' => $meta('property', 'og:description'),
                'site_name' => $meta('property', 'og:site_name'),
                'type' => $meta('property', 'og:type'),
            ]),
            'json_ld' => $jsonLd,
            'services' => $this->services($xpath),
            'city' => $city,
            'business_description' => $description ?? ($jsonLd[0]['description'] ?? null),
        ];
    }

    /** @return list<array{type: ?string, name: ?string, description: ?string, locality: ?string}> */
    private function jsonLd(DOMXPath $xpath): array
    {
        $out = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $data = json_decode($script->textContent, true);
            if (! is_array($data)) {
                continue;
            }
            $nodes = isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : (array_is_list($data) ? $data : [$data]);
            foreach ($nodes as $node) {
                if (! is_array($node) || ! isset($node['@type'])) {
                    continue;
                }
                $address = $node['address'] ?? null;
                if (is_array($address) && array_is_list($address)) {
                    $address = $address[0] ?? null;
                }
                $type = $node['@type'];
                $out[] = [
                    'type' => is_array($type) ? implode(',', array_filter($type, 'is_string')) : (is_string($type) ? $type : null),
                    'name' => isset($node['name']) && is_string($node['name']) ? Str::limit($this->clean($node['name']), 200, '') : null,
                    'description' => isset($node['description']) && is_string($node['description']) ? Str::limit($this->clean($node['description']), 400, '') : null,
                    'locality' => is_array($address) && isset($address['addressLocality']) && is_string($address['addressLocality']) ? $this->clean($address['addressLocality']) : null,
                ];
                if (count($out) >= 10) {
                    break 2;
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function services(DOMXPath $xpath): array
    {
        $candidates = [];
        foreach ($xpath->query('//h2|//h3|//nav//a|//li/a') as $node) {
            $value = $this->clean($node->textContent);
            $length = mb_strlen($value);
            if ($length < 4 || $length > 60 || str_word_count($value) > 7) {
                continue;
            }
            if (preg_match('/\b(login|log in|sign in|cart|contact|privacy|terms|cookie|home|about|blog|menu|search|careers?|faq)\b/i', $value)) {
                continue;
            }
            $candidates[mb_strtolower($value)] = $value;
            if (count($candidates) >= 12) {
                break;
            }
        }

        return array_values($candidates);
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }
}
