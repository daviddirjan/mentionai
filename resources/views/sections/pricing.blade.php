@php
    $currency = settings('report_currency', 'RON');
    $price = number_format(((int) settings('report_price', 9900)) / 100, 0, '.', ' ');
@endphp

<section id="pricing" class="border-y border-line bg-bg-soft">
    <div class="mx-auto max-w-5xl px-4 py-20 sm:px-6">
        <x-ui.section-header :eyebrow="__('site.pricing.eyebrow')" :title="__('site.pricing.title')" :subtitle="__('site.pricing.subtitle')" />
        <div class="mt-12 grid gap-5 md:grid-cols-2">
            <x-ui.card class="flex flex-col">
                <h3 class="font-semibold">{{ __('site.pricing.free_name') }}</h3>
                <p class="mt-3 text-4xl font-semibold tracking-tight">{{ __('site.pricing.free_price') }}</p>
                <p class="mt-3 flex-1 text-sm text-muted">{{ __('site.pricing.free_text') }}</p>
                <x-ui.button href="#analyze" variant="secondary" class="mt-6">{{ __('site.pricing.free_cta') }}</x-ui.button>
            </x-ui.card>

            <x-ui.card class="relative flex flex-col border-accent/40">
                <h3 class="font-semibold">{{ __('site.pricing.audit_name') }}</h3>
                <p class="mt-3 text-4xl font-semibold tracking-tight" data-testid="audit-price">{{ $price }} {{ $currency }} <span class="text-base font-normal text-muted">{{ __('site.pricing.one_time') }}</span></p>
                <p class="mt-3 flex-1 text-sm text-muted">{{ __('site.pricing.audit_text') }}</p>
                <x-ui.button :href="route('contact', ['subject' => 'geo_audit'])" class="mt-6">{{ __('site.pricing.audit_cta') }}</x-ui.button>
                <p class="mt-3 text-xs text-subtle">{{ __('site.pricing.audit_note') }}</p>
            </x-ui.card>
        </div>
    </div>
</section>
