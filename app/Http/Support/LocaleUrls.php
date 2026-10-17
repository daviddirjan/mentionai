<?php

namespace App\Http\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class LocaleUrls
{
    /** @return list<string> */
    public static function locales(): array
    {
        return config('mentionai.locales');
    }

    /** Native language names for the switcher. */
    public static function names(): array
    {
        return [
            'en' => 'English', 'ro' => 'Română', 'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch',
            'it' => 'Italiano', 'pt' => 'Português', 'nl' => 'Nederlands', 'pl' => 'Polski',
        ];
    }

    /** Detects the best supported locale from Accept-Language, defaulting to English. */
    public static function detect(Request $request): string
    {
        $preferred = $request->getPreferredLanguage(self::locales());

        return in_array($preferred, self::locales(), true) ? $preferred : (string) config('app.fallback_locale', 'en');
    }

    /** URL of the current public page in another locale (same route and parameters). */
    public static function forLocale(string $locale): string
    {
        $route = request()->route();
        $name = $route?->getName();

        if (! $name || ! Route::has($name)) {
            return route('home', ['locale' => $locale]);
        }

        return route($name, ['locale' => $locale] + $route->originalParameters());
    }

    /** @return array<string, string> locale => absolute URL, for hreflang alternates */
    public static function alternates(): array
    {
        return collect(self::locales())->mapWithKeys(fn (string $l) => [$l => self::forLocale($l)])->all();
    }
}
