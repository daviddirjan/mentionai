@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'bg-surface-2 text-muted border-line',
        'accent' => 'bg-surface-2 text-fg border-line-strong',
        'good' => 'bg-good/10 text-good border-good/30',
        'warn' => 'bg-warn/10 text-warn border-warn/30',
        'bad' => 'bg-bad/10 text-bad border-bad/30',
    ];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {$tones[$tone]}"]) }}>{{ $slot }}</span>
