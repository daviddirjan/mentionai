<?php

namespace App\Http\Controllers;

use App\Http\Support\LocaleUrls;
use App\Models\Analysis;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function redirectToLocale(Request $request): RedirectResponse
    {
        $response = redirect()->route('home', ['locale' => LocaleUrls::detect($request)]);
        $response->headers->set('Vary', 'Accept-Language');

        return $response;
    }

    public function home(): View
    {
        return view('pages.home');
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function privacy(): View
    {
        return view('pages.legal', ['page' => 'privacy']);
    }

    public function terms(): View
    {
        return view('pages.legal', ['page' => 'terms']);
    }

    public function cookies(): View
    {
        return view('pages.legal', ['page' => 'cookies']);
    }

    public function analysis(Analysis $analysis): View
    {
        return view('pages.analysis', ['uuid' => $analysis->uuid, 'company' => $analysis->company_name]);
    }
}
