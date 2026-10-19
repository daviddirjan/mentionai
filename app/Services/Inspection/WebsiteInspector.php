<?php

namespace App\Services\Inspection;

use Closure;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Fetches a homepage defensively (SSRF-safe) and extracts identity hints.
 * inspect() never throws: failures come back as ['fetched' => false, 'error' => ...].
 */
class WebsiteInspector
{
    private SafeFetcher $fetcher;

    /** @param  (Closure(string): list<string>)|null  $resolver host => list of IP addresses; injectable for tests */
    public function __construct(?Closure $resolver = null)
    {
        $this->fetcher = new SafeFetcher($resolver);
    }

    /** @return array<string, mixed> */
    public function inspect(string $url): array
    {
        $result = $this->fetcher->get($url, ['content_types' => ['text/html', 'application/xhtml']]);

        if (! $result->ok()) {
            return $this->failure($url, $result->error === 'content_type' ? 'not_html' : $result->error);
        }

        return ['fetched' => true, 'error' => null, 'url' => $url, 'final_url' => $result->finalUrl] + $this->extract((string) $result->body);
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
