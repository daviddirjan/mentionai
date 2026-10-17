@props(['value', 'size' => 200, 'label' => null])

@php
    $value = max(0, min(100, (float) $value));
    $radius = 52;
    $circumference = 2 * M_PI * $radius;
    $offset = $circumference * (1 - $value / 100);
    $gid = 'ring-'.substr(md5(uniqid('', true)), 0, 8);
@endphp

<div class="relative" style="width: {{ $size }}px; height: {{ $size }}px" role="img" aria-label="{{ $label ?? rtrim(rtrim(number_format($value, 1), '0'), '.').'%' }}">
    <svg viewBox="0 0 120 120" class="size-full -rotate-90">
        <defs>
            <linearGradient id="{{ $gid }}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="var(--accent)" />
                <stop offset="100%" stop-color="var(--accent-2)" />
            </linearGradient>
        </defs>
        <circle cx="60" cy="60" r="{{ $radius }}" fill="none" stroke="var(--line-strong)" stroke-width="9" />
        <circle cx="60" cy="60" r="{{ $radius }}" fill="none" stroke="url(#{{ $gid }})" stroke-width="9" stroke-linecap="round"
                stroke-dasharray="{{ round($circumference, 2) }}" stroke-dashoffset="{{ round($offset, 2) }}"
                style="transition: stroke-dashoffset 1s ease" />
    </svg>
    <div class="absolute inset-0 flex flex-col items-center justify-center">
        <span class="text-4xl font-semibold tracking-tight">{{ rtrim(rtrim(number_format($value, 1), '0'), '.') }}<span class="text-xl text-muted">%</span></span>
        @if ($label)
            <span class="mt-1 text-xs text-muted">{{ $label }}</span>
        @endif
    </div>
</div>
