<?php

namespace App\Support;

/**
 * Pure string helpers for website URLs. Performs no network access; SSRF protection
 * (DNS resolution, private IP blocking) lives in the website inspection service.
 */
class Domain
{
    /** Returns "https://host/path" or null when the URL is malformed or not http(s). */
    public static function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048) {
            return null;
        }
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = self::normalizeHost($parts['host']);
        if ($host === null) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = rtrim($parts['path'] ?? '', '/');

        return "{$scheme}://{$host}{$port}{$path}";
    }

    /** Returns the lowercase host without "www." (e.g. "axiontic.com"), or null when invalid. */
    public static function fromUrl(string $url): ?string
    {
        $normalized = self::normalizeUrl($url);
        if ($normalized === null) {
            return null;
        }

        $host = parse_url($normalized, PHP_URL_HOST);

        return preg_replace('/^www\./', '', $host);
    }

    private static function normalizeHost(string $host): ?string
    {
        $host = rtrim(strtolower($host), '.');
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $host)) {
            $host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: '';
        }

        // Public hostnames only: at least one dot, valid labels, no raw IP addresses.
        if (filter_var($host, FILTER_VALIDATE_IP) || ! str_contains($host, '.')) {
            return null;
        }
        if (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return null;
        }
        if (! preg_match('/\.[a-z][a-z0-9-]*[a-z0-9]$/', $host) && ! preg_match('/\.xn--[a-z0-9-]+$/', $host)) {
            return null;
        }

        return $host;
    }
}
