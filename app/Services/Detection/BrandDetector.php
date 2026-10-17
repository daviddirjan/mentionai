<?php

namespace App\Services\Detection;

use App\Data\MentionResult;
use App\Enums\MentionClassification;

/**
 * Deterministic brand detection. Ambiguity resolves to Uncertain, never to a positive class.
 */
class BrandDetector
{
    private const COMMON_WORDS = [
        'best', 'top', 'global', 'digital', 'group', 'solutions', 'services', 'systems', 'smart', 'first', 'prime',
        'alpha', 'beta', 'nova', 'one', 'pro', 'tech', 'web', 'soft', 'data', 'net', 'online', 'market', 'media',
        'studio', 'labs', 'agency', 'consulting', 'partners', 'express', 'direct', 'express', 'general', 'national',
        'central', 'premium', 'express', 'apple', 'orange', 'target', 'square', 'bridge', 'summit', 'pioneer',
    ];

    // Stems of "recommend / consider / top / best / leading / trusted" across the supported languages.
    private const RECOMMEND_PATTERN = '/recommend|\bconsider|\btop\b|\bbest\b|\bleading\b|\btrusted\b|\bsuggest|\bideal\b|\bhighly rated|\bstand[s]? out|recomand|recomien|recomend|recommand|empfehl|empfiehlt|\bbeste[nr]?\b|consigli|raccomand|\bmigliore|\bmelhor|\bmejor|\bmeilleur|aanbev|aanrad|\bpoleca|\bpolec|najlepsz|cel mai bun|cele mai bune|sugerez|sugiero|sugg[eè]r|vorschlag|\bvoorstel/iu';

    private const NEGATED_RECOMMEND = '/\b(not|never|no|n\'t|nicht|kein|nu|non|pas|nie)\b[^.!?\n]{0,25}(recommend|empfehl|recomand|consigli|recomien|recommand)/iu';

    /**
     * @param  list<array{url: string, title?: string|null}>  $citations
     * @param  list<string>  $variations
     */
    public function detect(string $text, array $citations, string $companyName, string $domain, array $variations = []): MentionResult
    {
        $foldedText = TextNormalizer::fold($text);
        $domain = strtolower(preg_replace('/^www\./', '', $domain));

        $terms = $this->nameTerms($companyName, $variations);
        $matches = [];   // list of [type, term, offsetChars, lengthChars]
        $matchedTerms = [];

        foreach ($terms as $type => $list) {
            foreach ($list as $label => $folded) {
                foreach ($this->find($foldedText, $folded) as $offset) {
                    $matches[] = ['type' => $type, 'term' => $label, 'offset' => $offset, 'length' => mb_strlen($folded)];
                    $matchedTerms[$label] = $label;
                }
            }
        }

        if ($domain !== '') {
            foreach ([$domain, 'www.'.$domain] as $variant) {
                foreach ($this->find($foldedText, $variant) as $offset) {
                    $matches[] = ['type' => 'domain', 'term' => $domain, 'offset' => $offset, 'length' => mb_strlen($variant)];
                    $matchedTerms[$domain] = $domain;
                }
            }
        }

        $citationEvidence = $this->citationEvidence($citations, $domain);

        $evidence = [];
        foreach (array_slice($matches, 0, 5) as $m) {
            $evidence[] = ['type' => $m['type'], 'term' => $m['term'], 'snippet' => $this->snippet($text, $m['offset'], $m['length'])];
        }
        $evidence = array_merge($evidence, $citationEvidence);

        // Name written in lowercase only, and the name is a common word: too ambiguous to count.
        if ($matches !== [] && $this->isAmbiguousCommonWord($text, $matches, $companyName)) {
            return new MentionResult(MentionClassification::Uncertain, array_values($matchedTerms), $evidence);
        }

        if ($matches !== []) {
            $recommended = false;
            foreach ($matches as $m) {
                if ($this->isRecommendationContext($text, $m['offset'], $m['length'])) {
                    $recommended = true;
                    break;
                }
            }

            return new MentionResult(
                $recommended ? MentionClassification::Recommended : MentionClassification::Mentioned,
                array_values($matchedTerms),
                $evidence,
            );
        }

        if ($citationEvidence !== []) {
            return new MentionResult(MentionClassification::CitedOnly, [$domain], $citationEvidence);
        }

        $partial = $this->partialMatches($text, $foldedText, $companyName);
        if ($partial !== []) {
            return new MentionResult(MentionClassification::Uncertain, array_column($partial, 'term'), $partial);
        }

        return new MentionResult(MentionClassification::NotMentioned);
    }

    /** @return array<string, array<string, string>> type => [label => folded term] */
    private function nameTerms(string $companyName, array $variations): array
    {
        $terms = ['name' => [], 'normalized' => [], 'variation' => []];

        $exact = trim(TextNormalizer::fold($companyName));
        if ($exact !== '') {
            $terms['name'][$companyName] = $exact;
        }

        $normalized = TextNormalizer::brandName($companyName);
        if ($normalized !== '' && $normalized !== $exact) {
            $terms['normalized'][$normalized] = $normalized;
        }

        foreach ($variations as $variation) {
            $folded = trim(TextNormalizer::fold((string) $variation));
            if (mb_strlen($folded) >= 2 && $folded !== $exact && $folded !== $normalized) {
                $terms['variation'][(string) $variation] = $folded;
            }
        }

        return $terms;
    }

    /** @return list<int> character offsets of boundary-delimited matches */
    private function find(string $foldedText, string $foldedTerm): array
    {
        if ($foldedTerm === '' || ! preg_match_all(TextNormalizer::boundaryPattern($foldedTerm), $foldedText, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        return array_map(fn ($hit) => mb_strlen(substr($foldedText, 0, $hit[1])), $m[0]);
    }

    /** @return list<array{type: string, term: string, snippet: string}> */
    private function citationEvidence(array $citations, string $domain): array
    {
        if ($domain === '') {
            return [];
        }
        $evidence = [];
        foreach ($citations as $citation) {
            $url = (string) ($citation['url'] ?? '');
            $title = trim((string) ($citation['title'] ?? ''));
            $host = TextNormalizer::domainHost($url);
            $titleHost = $title !== '' && ! str_contains($title, ' ') ? TextNormalizer::domainHost($title) : null;

            if (TextNormalizer::hostMatchesDomain($host, $domain) || ($titleHost !== null && TextNormalizer::hostMatchesDomain($titleHost, $domain))) {
                $evidence[] = ['type' => 'citation', 'term' => $domain, 'snippet' => mb_substr($url, 0, 200)];
            }
        }

        return array_slice($evidence, 0, 3);
    }

    private function isAmbiguousCommonWord(string $text, array $matches, string $companyName): bool
    {
        $normalized = TextNormalizer::brandName($companyName);
        if ($normalized === '' || str_contains($normalized, ' ') || ! in_array($normalized, self::COMMON_WORDS, true)) {
            return false;
        }

        // Only domain evidence is unambiguous; name matches must appear capitalized to count.
        foreach ($matches as $m) {
            if ($m['type'] === 'domain') {
                return false;
            }
            $original = mb_substr($text, $m['offset'], $m['length']);
            if ($original !== '' && mb_strtolower($original) !== $original) {
                return false;
            }
        }

        return true;
    }

    private function isRecommendationContext(string $text, int $offset, int $length): bool
    {
        $lineStart = mb_strrpos(mb_substr($text, 0, $offset), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = mb_strpos($text, "\n", $offset + $length);
        $lineEnd = $lineEnd === false ? mb_strlen($text) : $lineEnd;
        $line = mb_substr($text, $lineStart, $lineEnd - $lineStart);

        $isListItem = (bool) preg_match('/^\s*(?:[-*•–]|\d+[.)])\s+/u', $line);
        $isHeading = (bool) preg_match('/^\s*#{1,6}\s+/u', $line);

        $before = mb_substr($text, max(0, $offset - 2), 2);
        $after = mb_substr($text, $offset + $length, 2);
        $isBold = ($before === '**' && $after === '**') || ($before === '__' && $after === '__')
            || (bool) preg_match('/\*\*[^*\n]*'.preg_quote(mb_substr($text, $offset, $length), '/').'[^*\n]*\*\*/iu', $line);

        $sentence = $this->sentenceAround($text, $offset, $length);
        $negated = (bool) preg_match(self::NEGATED_RECOMMEND, $sentence);
        $recommendWords = ! $negated && preg_match(self::RECOMMEND_PATTERN, $sentence);

        if ($negated) {
            return false;
        }

        return $isListItem || $isHeading || $isBold || $recommendWords;
    }

    private function sentenceAround(string $text, int $offset, int $length): string
    {
        $before = mb_substr($text, 0, $offset);
        $start = 0;
        if (preg_match('/.*[.!?\n]\s/su', $before, $m)) {
            $start = mb_strlen($m[0]);
        }
        $after = mb_substr($text, $offset + $length);
        $end = preg_match('/[.!?\n]/u', $after, $m, PREG_OFFSET_CAPTURE) ? mb_strlen(substr($after, 0, $m[0][1])) : mb_strlen($after);

        return mb_substr($text, $start, ($offset + $length + $end) - $start);
    }

    private function snippet(string $text, int $offset, int $length): string
    {
        $start = max(0, $offset - 90);
        $snippet = mb_substr($text, $start, $length + 180);

        return trim(($start > 0 ? '…' : '').preg_replace('/\s+/u', ' ', $snippet));
    }

    /**
     * Fuzzy signals that must never count as positive:
     * - a longer different word that starts with the brand ("Axion" brand vs "Axiontic" in text)
     * - a capitalized word that is a prefix of the brand ("Axion" in text vs "Axiontic" brand)
     * - only the first word of a multi-word brand
     *
     * @return list<array{type: string, term: string, snippet: string}>
     */
    private function partialMatches(string $text, string $foldedText, string $companyName): array
    {
        $brand = TextNormalizer::brandName($companyName);
        if ($brand === '') {
            return [];
        }
        $brandTokens = explode(' ', $brand);
        $hits = [];

        // Walk the original text word by word so capitalization is available.
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $words, PREG_OFFSET_CAPTURE);
        foreach ($words[0] as [$word, $byteOffset]) {
            $folded = TextNormalizer::fold($word);
            $len = mb_strlen($folded);
            if ($len < 4) {
                continue;
            }
            $capitalized = mb_strtoupper(mb_substr($word, 0, 1)) === mb_substr($word, 0, 1);
            $charOffset = mb_strlen(substr($text, 0, $byteOffset));

            foreach ($brandTokens as $i => $token) {
                $tokenLen = mb_strlen($token);
                $singleWordBrand = count($brandTokens) === 1;
                $longerWord = $len > $tokenLen && str_starts_with($folded, $token) && $tokenLen >= 4;
                $prefixOfBrand = $capitalized && $len < $tokenLen && str_starts_with($token, $folded) && $len >= 4 && $len / $tokenLen >= 0.6;
                $firstWordOnly = ! $singleWordBrand && $i === 0 && $folded === $token && $capitalized
                    && $tokenLen >= 5 && ! in_array($token, self::COMMON_WORDS, true);

                if ($longerWord || ($singleWordBrand && $prefixOfBrand) || $firstWordOnly) {
                    $hits[] = ['type' => 'partial', 'term' => $word, 'snippet' => $this->snippet($text, $charOffset, $len)];
                    break;
                }
            }
        }

        return array_slice($hits, 0, 3);
    }
}
