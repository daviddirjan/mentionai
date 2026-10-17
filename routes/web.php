<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\SeoController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'redirectToLocale'])->name('root');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');

Route::prefix('{locale}')
    ->whereIn('locale', config('mentionai.locales'))
    ->middleware(SetLocale::class)
    ->group(function () {
        Route::get('/', [PageController::class, 'home'])->name('home');
        Route::get('contact', [PageController::class, 'contact'])->name('contact');
        Route::get('privacy-policy', [PageController::class, 'privacy'])->name('privacy');
        Route::get('terms-of-service', [PageController::class, 'terms'])->name('terms');
        Route::get('cookie-policy', [PageController::class, 'cookies'])->name('cookies');
        Route::get('analysis/{analysis:uuid}', [PageController::class, 'analysis'])
            ->whereUuid('analysis')
            ->name('analysis.show');
    });
