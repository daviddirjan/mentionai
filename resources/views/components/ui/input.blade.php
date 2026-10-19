@props(['label', 'name', 'hint' => null, 'required' => false, 'type' => 'text'])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-fg">
        {{ $label }}@if ($required)<span class="text-muted" aria-hidden="true"> *</span>@endif
    </label>
    <input id="{{ $name }}" type="{{ $type }}" @required($required)
           aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
           @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
           {{ $attributes->merge(['class' => 'h-12 w-full rounded-xl border border-line-strong bg-surface px-4 text-base text-fg placeholder:text-subtle transition focus:border-fg focus:outline-none focus:ring-2 focus:ring-signal/40 aria-[invalid=true]:border-bad']) }}>
    @if ($hint)
        <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-bad" role="alert">{{ $message }}</p>
    @enderror
</div>
