@props(['label', 'name', 'options' => [], 'required' => false, 'placeholder' => null])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">
        {{ $label }}@if ($required)<span class="text-accent" aria-hidden="true"> *</span>@endif
    </label>
    <select id="{{ $name }}" @required($required)
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            {{ $attributes->merge(['class' => 'h-11 w-full rounded-xl border border-line-strong bg-bg-soft px-3 text-sm text-fg transition focus:border-accent focus:outline-none']) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
    @error($name)
        <p class="mt-1 text-xs text-bad" role="alert">{{ $message }}</p>
    @enderror
</div>
