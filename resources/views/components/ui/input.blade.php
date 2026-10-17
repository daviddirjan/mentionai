@props(['label', 'name', 'hint' => null, 'required' => false, 'type' => 'text'])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">
        {{ $label }}@if ($required)<span class="text-accent" aria-hidden="true"> *</span>@endif
    </label>
    <input id="{{ $name }}" type="{{ $type }}" @required($required)
           aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
           {{ $attributes->merge(['class' => 'h-11 w-full rounded-xl border border-line-strong bg-bg-soft px-3.5 text-sm text-fg placeholder:text-subtle transition focus:border-accent focus:outline-none']) }}>
    @if ($hint)
        <p class="mt-1 text-xs text-subtle">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-bad" role="alert">{{ $message }}</p>
    @enderror
</div>
