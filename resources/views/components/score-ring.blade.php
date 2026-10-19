@props(['value', 'size' => 200, 'label' => null])

@php
    $value = max(0, min(100, (float) $value));
    $radius = 54;
    $circumference = 2 * M_PI * $radius;
    $offset = $circumference * (1 - $value / 100);
    $display = rtrim(rtrim(number_format($value, 1), '0'), '.');
@endphp

<div class="relative" style="width: {{ $size }}px; height: {{ $size }}px" role="img" aria-label="{{ $label ? $label.': ' : '' }}{{ $display }}%">
    <svg viewBox="0 0 120 120" class="size-full -rotate-90" aria-hidden="true">
        <circle cx="60" cy="60" r="{{ $radius }}" fill="none" stroke="var(--line)" stroke-width="5" />
        <circle cx="60" cy="60" r="{{ $radius }}" fill="none" stroke="var(--fg)" stroke-width="5" stroke-linecap="round"
                stroke-dasharray="{{ round($circumference, 2) }}" stroke-dashoffset="{{ round($offset, 2) }}"
                style="transition: stroke-dashoffset 1s ease" />
    </svg>
    <div class="absolute inset-0 flex flex-col items-center justify-center">
        <span class="text-5xl font-semibold tracking-tight text-fg">{{ $display }}<span class="text-2xl text-muted">%</span></span>
        @if ($label)
            <span class="mt-1 inline-flex items-center gap-1.5 text-xs uppercase tracking-widest text-muted"><span class="signal-dot size-1.5" aria-hidden="true"></span>{{ $label }}</span>
        @endif
    </div>
</div>
