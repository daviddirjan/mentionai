@props(['label', 'name', 'required' => false, 'rows' => 4])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium">
        {{ $label }}@if ($required)<span class="text-accent" aria-hidden="true"> *</span>@endif
    </label>
    <textarea id="{{ $name }}" rows="{{ $rows }}" @required($required)
              aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
              {{ $attributes->merge(['class' => 'w-full rounded-xl border border-line-strong bg-bg-soft px-3.5 py-3 text-sm text-fg placeholder:text-subtle transition focus:border-accent focus:outline-none']) }}></textarea>
    @error($name)
        <p class="mt-1 text-xs text-bad" role="alert">{{ $message }}</p>
    @enderror
</div>
