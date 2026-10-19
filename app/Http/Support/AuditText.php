<?php

namespace App\Http\Support;

use App\Data\Recommendation;
use App\Data\SiteCheck;
use App\Models\Analysis;

/**
 * Formatting helpers shared by the audit results page and the PDF.
 * Texts come only from lang/audit.php; backend params are merged over safe defaults
 * so a missing param never leaves a raw ":placeholder" in the output.
 */
class AuditText
{
    public static function check(SiteCheck $check, string $field, Analysis $analysis, string $lang): string
    {
        $key = "audit.check.{$check->key}.{$field}";
        $text = __($key, self::params($check->params, $analysis, $lang), $lang);

        // Unknown keys fall back to the key itself for titles and to nothing for impact.
        return $text === $key ? ($field === 'title' ? $check->key : '') : $text;
    }

    public static function rec(Recommendation $rec, string $field, Analysis $analysis, string $lang): string
    {
        $key = "audit.rec.{$rec->key}.{$field}";
        $text = __($key, self::params($rec->params, $analysis, $lang), $lang);

        return $text === $key ? ($field === 'action' ? $rec->key : '') : $text;
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @return array<string, string>
     */
    public static function params(array $params, Analysis $analysis, string $lang): array
    {
        $defaults = [
            'domain' => (string) $analysis->domain,
            'lang' => (string) $analysis->language,
            'location' => $analysis->location ?: self::marketName($analysis->market, $lang),
            'bots' => 'AI crawlers',
            'bot' => 'AI crawler',
            'competitors' => '—',
            'provider' => '—',
        ];

        $given = array_map(fn ($v) => (string) $v, array_filter($params, fn ($v) => $v !== null && $v !== ''));

        return array_merge($defaults, $given);
    }

    public static function marketName(?string $market, string $lang): string
    {
        return $market ? (Countries::options($lang)[$market] ?? $market) : '';
    }

    public static function percent(?float $value, string $lang): string
    {
        $formatted = rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');

        return ($lang === 'en' ? $formatted : str_replace('.', ',', $formatted)).'%';
    }

    /** One-line excerpt for tables: markdown markers stripped, whitespace collapsed, length capped. */
    public static function excerpt(?string $text, int $limit = 220): string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', str_replace(['**', '__', '`', '#'], '', (string) $text)));

        return mb_strlen($plain) > $limit ? rtrim(mb_substr($plain, 0, $limit - 1)).'…' : $plain;
    }
}
