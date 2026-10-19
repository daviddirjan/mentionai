@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'size' => 'md'])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-full font-medium transition duration-200 disabled:cursor-not-allowed disabled:opacity-60';
    $sizes = ['md' => 'h-11 px-5 text-[0.95rem]', 'lg' => 'h-12 px-7 text-base', 'sm' => 'h-9 px-4 text-sm'];
    $variants = [
        'primary' => 'bg-accent text-accent-fg hover:bg-accent-2',
        'secondary' => 'border border-line-strong bg-surface text-fg hover:border-fg',
        'ghost' => 'text-muted hover:text-fg',
    ];
    $classes = "$base {$sizes[$size]} {$variants[$variant]}";
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
