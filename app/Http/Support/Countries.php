<?php

namespace App\Http\Support;

use Collator;
use Locale;

class Countries
{
    private const CODES = [
        'AL', 'AD', 'AT', 'BY', 'BE', 'BA', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS',
        'IE', 'IT', 'XK', 'LV', 'LI', 'LT', 'LU', 'MT', 'MD', 'MC', 'ME', 'NL', 'MK', 'NO', 'PL', 'PT', 'RO', 'RS',
        'SK', 'SI', 'ES', 'SE', 'CH', 'UA', 'GB', 'US', 'CA', 'MX', 'BR', 'AR', 'CL', 'CO', 'PE', 'UY', 'AU', 'NZ',
        'IN', 'SG', 'JP', 'KR', 'CN', 'HK', 'TW', 'TH', 'VN', 'MY', 'ID', 'PH', 'AE', 'SA', 'QA', 'IL', 'TR', 'EG',
        'MA', 'ZA', 'NG', 'KE',
    ];

    /** @return array<string, string> ISO alpha-2 code => name localized to $locale, sorted by name */
    public static function options(string $locale): array
    {
        $names = [];
        foreach (self::CODES as $code) {
            $name = Locale::getDisplayRegion('-'.$code, $locale);
            $names[$code] = $name && $name !== $code ? $name : $code;
        }

        if (class_exists(Collator::class)) {
            $collator = new Collator($locale);
            uasort($names, fn ($a, $b) => $collator->compare($a, $b));
        } else {
            asort($names);
        }

        return $names;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return self::CODES;
    }
}
