@php
    $providerCount = count(\App\Enums\ProviderSlug::cases());
    $freeTests = (int) settings('query_count', 3) * $providerCount;
    $auditTests = (int) settings('audit_query_count', 25) * $providerCount;
@endphp

<section id="pricing" class="mx-auto max-w-5xl px-4 py-24 sm:px-6">
    <x-ui.section-header :eyebrow="__('site.pricing.eyebrow')" :title="__('site.pricing.title')" :subtitle="__('site.pricing.subtitle')" />
    <div class="mt-14 grid gap-5 md:grid-cols-2">
        <x-ui.card class="flex flex-col sm:p-9">
            <div class="flex h-8 items-center">
                <p class="eyebrow">{{ __('site.pricing.free_name') }}</p>
            </div>
            <p class="mt-5 text-5xl font-semibold tracking-tight text-fg">{{ $freeTests }}</p>
            <p class="mt-1 text-muted">{{ __('site.pricing.tests_label', ['providers' => $providerCount]) }}</p>
            <ul class="mt-6 flex-1 space-y-2.5 border-t border-line pt-6">
                @foreach (trans('site.pricing.free_items') as $item)
                    <li class="flex items-start gap-2.5 text-fg"><x-lucide-check class="mt-1 size-4 shrink-0 text-good" />{{ $item }}</li>
                @endforeach
            </ul>
            <x-ui.button href="#analyze" variant="secondary" class="mt-8">{{ __('site.pricing.free_cta') }}</x-ui.button>
        </x-ui.card>

        <x-ui.card class="relative flex flex-col border-fg sm:p-9" data-testid="audit-plan">
            <div class="flex h-8 items-center justify-between gap-3">
                <p class="eyebrow text-fg">{{ __('site.pricing.audit_name') }}</p>
                <x-ui.badge tone="accent"><span class="signal-dot size-1.5" aria-hidden="true"></span>{{ __('site.pricing.audit_badge') }}</x-ui.badge>
            </div>
            <p class="mt-5 text-5xl font-semibold tracking-tight text-fg">{{ $auditTests }}</p>
            <p class="mt-1 text-muted">{{ __('site.pricing.tests_label', ['providers' => $providerCount]) }}</p>
            <ul class="mt-6 flex-1 space-y-2.5 border-t border-line pt-6">
                @foreach (trans('site.pricing.audit_items') as $item)
                    <li class="flex items-start gap-2.5 text-fg"><x-lucide-check class="mt-1 size-4 shrink-0 text-good" />{{ $item }}</li>
                @endforeach
            </ul>
            <p class="mt-6 text-sm text-muted">{{ __('site.pricing.audit_note') }}</p>
            <x-ui.button href="#analyze" class="mt-8">{{ __('site.pricing.audit_cta') }}</x-ui.button>
        </x-ui.card>
    </div>
</section>
