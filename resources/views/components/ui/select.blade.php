@props(['label', 'name', 'options' => [], 'required' => false, 'placeholder' => null])

<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-fg">
        {{ $label }}@if ($required)<span class="text-muted" aria-hidden="true"> *</span>@endif
    </label>
    <div class="relative">
    <select id="{{ $name }}" @required($required)
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            @if ($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
            {{ $attributes->merge(['class' => 'h-12 w-full appearance-none rounded-xl border border-line-strong bg-surface pl-4 pr-12 text-base text-fg transition focus:border-fg focus:outline-none focus:ring-2 focus:ring-signal/40 aria-[invalid=true]:border-bad']) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
    <x-lucide-chevron-down class="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
    </div>
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-bad" role="alert">{{ $message }}</p>
    @enderror
</div>
