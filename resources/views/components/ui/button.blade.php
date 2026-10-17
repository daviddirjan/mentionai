@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'size' => 'md'])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl font-medium transition duration-200 disabled:cursor-not-allowed disabled:opacity-60';
    $sizes = ['md' => 'h-11 px-5 text-sm', 'lg' => 'h-12 px-6 text-base', 'sm' => 'h-9 px-3.5 text-sm'];
    $variants = [
        'primary' => 'bg-gradient-to-r from-accent to-accent-2 text-white shadow-lg shadow-accent/20 hover:brightness-110',
        'secondary' => 'border border-line-strong bg-surface text-fg hover:bg-surface-2',
        'ghost' => 'text-muted hover:text-fg',
    ];
    $classes = "$base {$sizes[$size]} {$variants[$variant]}";
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
