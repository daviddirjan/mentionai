<?php

namespace App\Services\Inspection;

use Closure;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * SSRF-safe HTTP GET for user-supplied sites. Every hop is validated (scheme, port, no userinfo, all resolved IPs
 * public) and the connection is pinned to the validated IP so DNS cannot be re-bound between check and connect.
 * Redirects are followed manually, the body is capped mid-transfer and the whole fetch has one deadline.
 * Never throws.
 */
class SafeFetcher
{
    private const REDIRECTS = [301, 302, 303, 307, 308];

    /** @var Closure(string): list<string> */
    private Closure $resolver;

    /** @param  (Closure(string): list<string>)|null  $resolver host => list of IP addresses; injectable for tests */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? fn (string $host): array => self::resolveDns($host);
    }

    /**
     * Options: accept (Accept header); content_types (accepted Content-Type substrings, [] = any, a missing header
     * is always accepted); same_host (refuse any hop to another host); deadline (absolute microtime(true) shared
     * by several fetches); max_bytes (defaults to the inspection cap).
     *
     * @param  array{accept?: string, content_types?: list<string>, same_host?: ?string, deadline?: ?float, max_bytes?: ?int}  $options
     */
    public function get(string $url, array $options = []): FetchResult
    {
        $config = config('mentionai.website_inspection');
        $started = microtime(true);
        $deadline = min($options['deadline'] ?? INF, $started + (float) $config['timeout_seconds']);
        $maxBytes = (int) ($options['max_bytes'] ?? $config['max_bytes']);
        $contentTypes = $options['content_types'] ?? [];
        $sameHost = isset($options['same_host']) ? strtolower($options['same_host']) : null;
        $current = $url;
        $tooLarge = false;
        $redirects = 0;

        $result = function (?string $error, ?int $status = null, array $headers = [], ?string $body = null) use ($url, &$current, $started, &$redirects): FetchResult {
            return new FetchResult($url, $current, $status, $headers, $body, (int) round((microtime(true) - $started) * 1000), $redirects, $error);
        };

        try {
            for ($hop = 0; $hop <= (int) $config['max_redirects']; $hop++) {
                $target = $this->validate($current);
                if ($sameHost !== null && $target['host'] !== $sameHost) {
                    return $result('off_host');
                }
                $remaining = max(1.0, $deadline - microtime(true));
                if ($remaining <= 1.0 && microtime(true) >= $deadline) {
                    return $result('timeout');
                }

                $response = Http::withHeaders(['User-Agent' => $config['user_agent'], 'Accept' => $options['accept'] ?? 'text/html,application/xhtml+xml'])
                    ->timeout((int) ceil($remaining))
                    ->withOptions([
                        'allow_redirects' => false,
                        // Abort mid-transfer once the cap is passed; Content-Length may be absent or wrong.
                        'progress' => function ($downloadTotal, $downloaded) use ($maxBytes, &$tooLarge): void {
                            if ($downloaded > $maxBytes) {
                                $tooLarge = true;
                                throw new RuntimeException('too_large');
                            }
                        },
                        'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:".(str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip'])]],
                    ])
                    ->get($current);

                $status = $response->status();
                $headers = array_map(fn ($values) => is_array($values) ? (string) ($values[0] ?? '') : (string) $values, array_change_key_case($response->headers(), CASE_LOWER));

                if (in_array($status, self::REDIRECTS, true)) {
                    $location = $response->header('Location');
                    if ($location === '') {
                        return $result('redirect_without_location', $status, $headers);
                    }
                    $current = self::absoluteUrl($current, $location);
                    $redirects++;

                    continue;
                }

                if ($status < 200 || $status >= 300) {
                    return $result("http_{$status}", $status, $headers);
                }

                $contentType = strtolower($response->header('Content-Type'));
                if ($contentType !== '' && $contentTypes !== [] && ! array_filter($contentTypes, fn (string $t) => str_contains($contentType, $t))) {
                    return $result('content_type', $status, $headers);
                }

                $body = $response->body();
                if ((int) $response->header('Content-Length') > $maxBytes || strlen($body) > $maxBytes) {
                    return $result('too_large', $status, $headers);
                }

                return $result(null, $status, $headers, $body);
            }

            return $result('too_many_redirects');
        } catch (UnsafeUrl) {
            return $result('unsafe_url');
        } catch (Throwable) {
            return $result($tooLarge ? 'too_large' : 'fetch_failed');
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
    private static function resolveDns(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($ips));
    }

    public static function absoluteUrl(string $base, string $location): string
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
}
