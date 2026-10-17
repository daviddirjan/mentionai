<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SeoController extends Controller
{
    private const PAGES = ['home', 'contact', 'privacy', 'terms', 'cookies'];

    public function sitemap(): Response
    {
        $locales = config('mentionai.locales');
        $urls = [];

        foreach (self::PAGES as $page) {
            $alternates = [];
            foreach ($locales as $locale) {
                $alternates[$locale] = route($page, ['locale' => $locale]);
            }
            foreach ($alternates as $url) {
                $urls[] = ['loc' => $url, 'alternates' => $alternates, 'priority' => $page === 'home' ? '1.0' : '0.5'];
            }
        }

        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Disallow: /admin', 'Disallow: /livewire'];
        foreach (config('mentionai.locales') as $locale) {
            $lines[] = "Disallow: /{$locale}/analysis/";
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
