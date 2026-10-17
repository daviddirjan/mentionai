@props(['eyebrow' => null, 'title', 'subtitle' => null, 'align' => 'center'])

<header @class(['max-w-2xl', 'mx-auto text-center' => $align === 'center'])>
    @if ($eyebrow)
        <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-accent">{{ $eyebrow }}</p>
    @endif
    <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ $title }}</h2>
    @if ($subtitle)
        <p class="mt-4 text-base text-muted sm:text-lg">{{ $subtitle }}</p>
    @endif
</header>
