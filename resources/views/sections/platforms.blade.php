<section id="platforms" class="border-y border-line bg-bg-soft">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <x-ui.section-header :eyebrow="__('site.platforms.eyebrow')" :title="__('site.platforms.title')" :subtitle="__('site.platforms.subtitle')" />
        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (\App\Enums\ProviderSlug::cases() as $slug)
                <x-ui.card class="h-full hover:border-line-strong">
                    <span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-accent/20 to-accent-2/20 text-accent"><x-lucide-bot class="size-5" /></span>
                    <h3 class="mt-4 text-lg font-semibold">{{ $slug->displayName() }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted">{{ __('site.platforms.items.'.$slug->value) }}</p>
                </x-ui.card>
            @endforeach
        </div>
    </div>
</section>
