@props(['title' => null, 'description' => null, 'noindex' => false, 'schema' => []])

@php
    $site = settings('site_name', 'MentionAI');
    $fullTitle = $title ? $title.' — '.$site : (string) __('site.meta.title');
    $desc = $description ?: __('site.meta.description');
    $canonical = \App\Http\Support\LocaleUrls::forLocale(app()->getLocale());
    $locale = app()->getLocale();
    $ogLocale = str_replace('-', '_', $locale).'_'.strtoupper($locale === 'en' ? 'us' : $locale);
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $desc }}">
<meta name="robots" content="{{ $noindex ? 'noindex, nofollow' : 'index, follow' }}">
<link rel="canonical" href="{{ $canonical }}">

@unless ($noindex)
    @foreach (\App\Http\Support\LocaleUrls::alternates() as $alt => $href)
        <link rel="alternate" hreflang="{{ $alt }}" href="{{ $href }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ \App\Http\Support\LocaleUrls::forLocale('en') }}">
@endunless

<meta property="og:site_name" content="{{ $site }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $desc }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ $ogLocale }}">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $desc }}">

@foreach ($schema as $block)
    <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
