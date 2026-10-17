@props(['title' => null, 'description' => null, 'noindex' => false, 'schema' => []])

@php($locale = app()->getLocale())
<!DOCTYPE html>
<html lang="{{ $locale }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (!t) { t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'; }
            document.documentElement.dataset.theme = t;
        })();
    </script>
    <x-seo :title="$title" :description="$description" :noindex="$noindex" :schema="$schema" />
    <link rel="icon" href="/favicon.ico">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (config('mentionai.turnstile.site_key'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
    @endif
</head>
<body class="min-h-screen antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2">{{ __('site.nav.skip') }}</a>

    <header class="sticky top-0 z-40 border-b border-line bg-bg/80 backdrop-blur-xl">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                <span class="grid size-8 place-items-center rounded-lg bg-gradient-to-br from-accent to-accent-2 text-white">
                    <x-lucide-sparkles class="size-4" />
                </span>
                {{ settings('site_name', 'MentionAI') }}
            </a>

            <nav class="hidden items-center gap-6 text-sm text-muted lg:flex" aria-label="{{ __('site.nav.main') }}">
                <a class="transition hover:text-fg" href="{{ route('home') }}#how">{{ __('site.nav.how') }}</a>
                <a class="transition hover:text-fg" href="{{ route('home') }}#platforms">{{ __('site.nav.platforms') }}</a>
                <a class="transition hover:text-fg" href="{{ route('home') }}#pricing">{{ __('site.nav.pricing') }}</a>
                <a class="transition hover:text-fg" href="{{ route('home') }}#faq">{{ __('site.nav.faq') }}</a>
                <a class="transition hover:text-fg" href="{{ route('home') }}#contact">{{ __('site.nav.contact') }}</a>
            </nav>

            <div class="flex items-center gap-2">
                <details class="relative">
                    <summary class="flex h-9 cursor-pointer list-none items-center gap-1.5 rounded-lg border border-line px-2.5 text-sm text-muted transition hover:text-fg" aria-label="{{ __('site.nav.language') }}">
                        <x-lucide-globe class="size-4" />
                        <span class="uppercase">{{ $locale }}</span>
                    </summary>
                    <ul class="absolute right-0 mt-2 w-44 rounded-xl border border-line bg-surface p-1 shadow-xl">
                        @foreach (\App\Http\Support\LocaleUrls::names() as $code => $name)
                            <li>
                                <a href="{{ \App\Http\Support\LocaleUrls::forLocale($code) }}" hreflang="{{ $code }}" lang="{{ $code }}"
                                   @class(['flex items-center justify-between rounded-lg px-3 py-2 text-sm transition hover:bg-surface-2', 'text-fg font-medium' => $code === $locale, 'text-muted' => $code !== $locale])>
                                    {{ $name }}
                                    @if ($code === $locale)
                                        <x-lucide-check class="size-4 text-accent" />
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>

                <button type="button" onclick="mentionaiToggleTheme()" class="grid size-9 place-items-center rounded-lg border border-line text-muted transition hover:text-fg" aria-label="{{ __('site.nav.theme') }}">
                    <x-lucide-sun class="hidden size-4 [[data-theme=dark]_&]:block" />
                    <x-lucide-moon class="size-4 [[data-theme=dark]_&]:hidden" />
                </button>

                <a href="{{ route('home') }}#analyze" class="hidden h-9 items-center rounded-lg bg-fg px-3.5 text-sm font-medium text-bg transition hover:opacity-90 sm:inline-flex">{{ __('site.nav.cta') }}</a>
            </div>
        </div>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    <footer class="border-t border-line">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-[1.5fr_1fr_1fr]">
            <div>
                <p class="font-semibold">{{ settings('site_name', 'MentionAI') }}</p>
                <p class="mt-2 max-w-sm text-sm text-muted">{{ __('site.footer.tagline') }}</p>
            </div>
            <nav aria-label="{{ __('site.footer.legal') }}">
                <p class="text-sm font-medium">{{ __('site.footer.legal') }}</p>
                <ul class="mt-3 space-y-2 text-sm text-muted">
                    <li><a class="transition hover:text-fg" href="{{ route('privacy') }}">{{ __('site.legal.privacy.title') }}</a></li>
                    <li><a class="transition hover:text-fg" href="{{ route('terms') }}">{{ __('site.legal.terms.title') }}</a></li>
                    <li><a class="transition hover:text-fg" href="{{ route('cookies') }}">{{ __('site.legal.cookies.title') }}</a></li>
                </ul>
            </nav>
            <nav aria-label="{{ __('site.footer.company') }}">
                <p class="text-sm font-medium">{{ __('site.footer.company') }}</p>
                <ul class="mt-3 space-y-2 text-sm text-muted">
                    <li><a class="transition hover:text-fg" href="{{ route('contact') }}">{{ __('site.nav.contact') }}</a></li>
                    <li><a class="transition hover:text-fg" href="{{ route('contact', ['subject' => 'data_deletion']) }}">{{ __('site.footer.deletion') }}</a></li>
                </ul>
            </nav>
        </div>
        <div class="border-t border-line py-6 text-center text-sm text-subtle">© MentionAI. Developed by Axiontic SRL.</div>
    </footer>
</body>
</html>
