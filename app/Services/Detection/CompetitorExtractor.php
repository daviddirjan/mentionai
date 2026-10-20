<?php

namespace App\Services\Detection;

use Illuminate\Support\Str;

/**
 * Extracts competing company names from markdown-ish AI answers (list items, bold text, headings)
 * and from citation domains. Deterministic; no external calls.
 */
class CompetitorExtractor
{
    private const EXCLUDED_NAMES = [
        'openai', 'chatgpt', 'gpt', 'gemini', 'google', 'claude', 'anthropic', 'perplexity', 'sonar', 'copilot', 'bing', 'mentionai',
    ];

    private const GENERIC_PREFIXES = [
        'top', 'best', 'here', 'why', 'how', 'what', 'which', 'key', 'note', 'tip', 'tips', 'pros', 'cons', 'summary',
        'conclusion', 'overview', 'recommendation', 'recommendations', 'sources', 'source', 'disclaimer', 'important',
        'additional', 'other', 'options', 'option', 'factors', 'consider', 'considerations', 'final', 'quick', 'general',
        'services', 'service', 'features', 'feature', 'benefits', 'location', 'pricing', 'price', 'contact', 'website',
        'specialties', 'specialization', 'strengths', 'weaknesses', 'when', 'if', 'for', 'with', 'in', 'as', 'based',
        'step', 'steps', 'example', 'examples', 'companies', 'company', 'agencies', 'providers', 'also', 'these', 'this',
        'cele', 'cei', 'iată', 'iata', 'aquí', 'aqui', 'voici', 'hier', 'ecco', 'aqui', 'oto', 'hier',
    ];

    // Aggregators, directories, social and reference sites are sources, not competitors.
    private const NON_COMPETITOR_DOMAINS = [
        'wikipedia.org', 'linkedin.com', 'facebook.com', 'instagram.com', 'twitter.com', 'x.com', 'youtube.com',
        'reddit.com', 'quora.com', 'medium.com', 'clutch.co', 'g2.com', 'capterra.com', 'trustpilot.com',
        'goodfirms.co', 'glassdoor.com', 'indeed.com', 'yelp.com', 'forbes.com', 'google.com', 'bing.com',
        'github.com', 'crunchbase.com', 'tripadvisor.com', 'amazon.com', 'wordpress.com', 'pinterest.com',
        'vertexaisearch.cloud.google.com', 'openai.com', 'anthropic.com', 'perplexity.ai',
    ];

    private const MAX_RESULTS = 25;

    /**
     * @param  iterable<object{provider: string, response_text: ?string, citations: ?array}>  $responses successful responses
     * @param  list<string>  $variations
     * @return list<array{name: string, normalized_name: string, domain: ?string, mention_count: int, providers: list<string>}>
     */
    public function extract(iterable $responses, string $companyName, string $domain, array $variations = []): array
    {
        $brandKeys = array_filter(array_map(TextNormalizer::key(...), [$companyName, ...$variations]));
        $domain = strtolower(preg_replace('/^www\./', '', $domain));
        $domainKey = TextNormalizer::key(explode('.', $domain)[0] ?? '');
        if ($domainKey !== '') {
            $brandKeys[] = $domainKey;
            $brandKeys[] = TextNormalizer::key($domain);
        }

        $found = []; // key => [name, domain, count, providers, nameVotes]

        foreach ($responses as $response) {
            $provider = (string) $response->provider;
            $seenInResponse = [];

            foreach ($this->namesFromText((string) $response->response_text) as $name) {
                $key = TextNormalizer::key($name);
                if (! $this->acceptable($name, $key, $brandKeys)) {
                    continue;
                }
                $seenInResponse[$key] ??= ['name' => $name, 'domain' => null];
            }

            foreach ($response->citations ?? [] as $citation) {
                $host = TextNormalizer::domainHost((string) ($citation['url'] ?? ''));
                if ($host === null || TextNormalizer::hostMatchesDomain($host, $domain) || $this->isNonCompetitorHost($host)) {
                    continue;
                }
                $label = explode('.', $host)[0];
                $key = TextNormalizer::key($label);
                if ($key === '' || in_array($key, $brandKeys, true) || in_array($key, self::EXCLUDED_NAMES, true)) {
                    continue;
                }
                // Attach the domain to a name found in text when they line up, else add the domain as its own entry.
                $match = collect(array_keys($seenInResponse))->first(fn ($k) => $k === $key || (strlen($key) >= 5 && str_contains($k, $key)) || (strlen($k) >= 5 && str_contains($key, $k)));
                if ($match !== null) {
                    $seenInResponse[$match]['domain'] ??= $host;
                } else {
                    $seenInResponse[$key] = ['name' => Str::headline($label), 'domain' => $host];
                }
            }

            foreach ($seenInResponse as $key => $entry) {
                $found[$key] ??= ['name' => $entry['name'], 'domain' => null, 'count' => 0, 'providers' => []];
                $found[$key]['count']++;
                $found[$key]['providers'][$provider] = $provider;
                $found[$key]['domain'] ??= $entry['domain'];
                // Prefer the nicer (text-derived, longer, cased) spelling.
                if ($entry['domain'] === null && mb_strlen($entry['name']) >= mb_strlen($found[$key]['name'])) {
                    $found[$key]['name'] = $entry['name'];
                }
            }
        }

        uasort($found, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        $out = [];
        foreach (array_slice($found, 0, self::MAX_RESULTS, true) as $key => $entry) {
            $out[] = [
                'name' => $entry['name'],
                'normalized_name' => $key,
                'domain' => $entry['domain'],
                'mention_count' => $entry['count'],
                'providers' => array_values($entry['providers']),
            ];
        }

        return $out;
    }

    /** @return list<string> */
    private function namesFromText(string $text): array
    {
        $names = [];
        $text = mb_scrub($text, 'UTF-8');

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $item = null;
            if (preg_match('/^(?:[-*•–]|\d+[.)])\s+(.*)$/u', $line, $m)) {
                $item = $m[1];
            } elseif (preg_match('/^#{1,6}\s+(.*)$/u', $line, $m)) {
                $item = $m[1];
            }

            // Every bold segment anywhere is a candidate.
            if (preg_match_all('/\*\*([^*\n]{2,80})\*\*|__([^_\n]{2,80})__/u', $line, $bold)) {
                foreach (array_merge($bold[1], $bold[2]) as $segment) {
                    if ($segment !== '') {
                        $names[] = $this->clean($segment);
                    }
                }
                if ($item !== null && preg_match('/\*\*|__/u', $item)) {
                    continue; // bold already handled for this item
                }
            }

            if ($item !== null) {
                $names[] = $this->clean(preg_split('/\s[—–-]\s|:\s|\s\(|,\s|\s\|\s/u', $item)[0] ?? $item);
            }
        }

        return array_values(array_filter($names));
    }

    private function clean(string $value): string
    {
        $value = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $value);   // markdown links
        $value = preg_replace('/\[\d+\]|\(\d+\)/u', '', $value);            // citation markers
        $value = preg_replace('/[*_`#]+/u', '', $value);
        $value = preg_replace('/^\d+[.)]\s*/u', '', trim($value));
        // trim() is byte-based and would cut multibyte letters such as "ș" in half, so strip by code point.
        $value = preg_replace('/^[\s:–—\-.,;!?"\'“”‘’()]+|[\s:–—\-.,;!?"\'“”‘’()]+$/u', '', $value);

        return (string) preg_replace('/\s+/u', ' ', (string) $value);
    }

    /** @param  list<string>  $brandKeys */
    private function acceptable(string $name, string $key, array $brandKeys): bool
    {
        $length = mb_strlen($name);
        $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);

        if ($key === '' || $length < 2 || $length > 60 || count($words) > 5 || preg_match('/^\d+$/', $key)) {
            return false;
        }
        // Real company names start with an uppercase letter or digit.
        if (! preg_match('/^[\p{Lu}\p{N}]/u', $name)) {
            return false;
        }
        if (preg_match('/[?:]$/u', $name) || preg_match('/\b(https?:|www\.)/i', $name)) {
            return false;
        }

        $first = TextNormalizer::fold($words[0]);
        if (in_array($first, self::GENERIC_PREFIXES, true) && (count($words) > 1 || mb_strlen($first) > 0)) {
            return false;
        }
        if (in_array($key, self::EXCLUDED_NAMES, true) || in_array($key, $brandKeys, true)) {
            return false;
        }
        foreach ($brandKeys as $brandKey) {
            if (strlen($brandKey) >= 4 && TextNormalizer::key($name) === $brandKey) {
                return false;
            }
        }
        foreach ($words as $word) {
            if (in_array(TextNormalizer::fold($word), self::EXCLUDED_NAMES, true) && count($words) === 1) {
                return false;
            }
        }

        return true;
    }

    private function isNonCompetitorHost(string $host): bool
    {
        foreach (self::NON_COMPETITOR_DOMAINS as $blocked) {
            if (TextNormalizer::hostMatchesDomain($host, $blocked)) {
                return true;
            }
        }

        return false;
    }
}
