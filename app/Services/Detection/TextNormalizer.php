<?php

namespace App\Services\Detection;

use Illuminate\Support\Str;
use Transliterator;

/** Case/diacritic folding and brand-name normalization shared by detection, competitors and question safety. */
final class TextNormalizer
{
    private const LEGAL_SUFFIXES = ['srl', 'sa', 'sas', 'sarl', 'llc', 'ltd', 'limited', 'gmbh', 'inc', 'incorporated', 'corp', 'co', 'bv', 'spa', 'sl', 'ag', 'ab', 'oy', 'plc', 'pty', 'srls', 'sc', 'sp', 'zoo'];

    private static ?Transliterator $folder = null;

    /** Lowercase and strip diacritics while keeping the character count stable (so offsets map back to the original). */
    public static function fold(string $text): string
    {
        self::$folder ??= class_exists(Transliterator::class)
            ? Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower()')
            : null;

        $folded = self::$folder?->transliterate($text);
        if ($folded === false || $folded === null) {
            return mb_strtolower(Str::ascii($text));
        }

        return str_replace(['ß', 'ø', 'đ', 'ł'], ['s', 'o', 'd', 'l'], $folded);
    }

    /** Folded name without legal suffixes, e.g. "Axiontic S.R.L." => "axiontic". */
    public static function brandName(string $name): string
    {
        $folded = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', str_replace('.', '', self::fold($name))));
        $tokens = $folded === '' ? [] : explode(' ', $folded);

        while (count($tokens) > 1 && in_array(end($tokens), self::LEGAL_SUFFIXES, true)) {
            array_pop($tokens);
        }

        return implode(' ', $tokens);
    }

    /** Dedupe key: brandName without spaces. */
    public static function key(string $name): string
    {
        return str_replace(' ', '', self::brandName($name));
    }

    public static function domainHost(string $urlOrHost): ?string
    {
        $host = parse_url(str_contains($urlOrHost, '://') ? $urlOrHost : 'https://'.$urlOrHost, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower($host));
    }

    /** Whether $host is $domain or one of its subdomains. */
    public static function hostMatchesDomain(?string $host, string $domain): bool
    {
        if ($host === null || $host === '') {
            return false;
        }
        $domain = preg_replace('/^www\./', '', strtolower($domain));

        return $host === $domain || str_ends_with($host, '.'.$domain);
    }

    /** Unicode-aware word-boundary regex for a folded term. */
    public static function boundaryPattern(string $foldedTerm): string
    {
        return '/(?<![\p{L}\p{N}])'.preg_quote($foldedTerm, '/').'(?![\p{L}\p{N}])/u';
    }
}
