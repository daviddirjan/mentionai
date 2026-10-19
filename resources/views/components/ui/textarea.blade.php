@props(['label', 'name', 'required' => false, 'rows' => 4])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-fg">
        {{ $label }}@if ($required)<span class="text-muted" aria-hidden="true"> *</span>@endif
    </label>
    <textarea id="{{ $name }}" rows="{{ $rows }}" @required($required)
              aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
              {{ $attributes->merge(['class' => 'w-full rounded-xl border border-line-strong bg-surface px-4 py-3 text-base text-fg placeholder:text-subtle transition focus:border-fg focus:outline-none focus:ring-2 focus:ring-signal/40 aria-[invalid=true]:border-bad']) }}></textarea>
    @error($name)
        <p class="mt-1 text-sm text-bad" role="alert">{{ $message }}</p>
    @enderror
</div>
