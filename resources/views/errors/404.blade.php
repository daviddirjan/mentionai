@php(\Illuminate\Support\Facades\URL::defaults(['locale' => in_array(app()->getLocale(), config('mentionai.locales'), true) ? app()->getLocale() : 'en']))
<x-layouts.app :title="__('site.error.not_found_title')" :noindex="true">
    <div class="mx-auto max-w-xl px-4 py-24 text-center">
        <p class="text-sm font-semibold text-accent">404</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight">{{ __('site.error.not_found_title') }}</h1>
        <p class="mt-3 text-muted">{{ __('site.error.not_found_text') }}</p>
        <x-ui.button :href="route('root')" class="mt-8">{{ __('site.common.back_home') }}</x-ui.button>
    </div>
</x-layouts.app>
