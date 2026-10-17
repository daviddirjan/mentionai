<section class="relative overflow-hidden">
    <div class="bg-grid pointer-events-none absolute inset-0" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-6xl px-4 pb-16 pt-16 sm:px-6 sm:pt-24">
        <div class="mx-auto max-w-3xl text-center">
            <x-ui.badge tone="accent" class="mb-6"><x-lucide-sparkles class="size-3.5" /> {{ __('site.hero.badge') }}</x-ui.badge>
            <h1 class="text-gradient text-4xl font-semibold tracking-tight sm:text-6xl">{{ __('site.hero.title') }}</h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-muted sm:text-xl">{{ __('site.hero.description') }}</p>
            <div class="mt-8 flex flex-col items-center gap-3">
                <x-ui.button href="#analyze" size="lg">{{ __('site.hero.cta') }} <x-lucide-arrow-down class="size-5" /></x-ui.button>
                <p class="text-sm text-subtle">{{ __('site.hero.note') }}</p>
            </div>
        </div>

        <div class="mx-auto mt-14 max-w-3xl">
            <livewire:analysis-form />
        </div>
    </div>
</section>
