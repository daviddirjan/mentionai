<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->route('locale');

        if (! in_array($locale, config('mentionai.locales'), true)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        $request->route()?->forgetParameter('locale');

        return $next($request);
    }
}
