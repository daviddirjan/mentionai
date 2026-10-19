@props(['eyebrow' => null, 'title', 'subtitle' => null, 'align' => 'center', 'number' => null])

<header @class(['max-w-2xl', 'mx-auto text-center' => $align === 'center'])>
    @if ($eyebrow)
        <p class="eyebrow mb-4 inline-flex items-center gap-2">
            @if ($number)<span class="font-mono text-fg">{{ $number }}</span>@else<span class="signal-dot" aria-hidden="true"></span>@endif
            {{ $eyebrow }}
        </p>
    @endif
    <h2 class="text-3xl font-semibold leading-tight text-fg sm:text-[2.6rem]">{{ $title }}</h2>
    @if ($subtitle)
        <p class="mt-4 text-lg text-muted">{{ $subtitle }}</p>
    @endif
</header>
