@php
    $site = settings('site_name', 'MentionAI');
    $schema = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $site,
            'legalName' => 'Axiontic SRL',
            'url' => route('home'),
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $site,
            'url' => route('home'),
            'inLanguage' => app()->getLocale(),
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect(trans('site.faq.items'))->map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
            ])->all(),
        ],
    ];
@endphp

<x-layouts.app :schema="$schema">
    @include('sections.hero')
    @include('sections.how')
    @include('sections.platforms')
    @include('sections.why')
    @include('sections.example')
    @include('sections.features')
    @include('sections.pricing')
    @include('sections.faq')
    @include('sections.contact')
</x-layouts.app>
