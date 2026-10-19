<section id="platforms" class="border-y border-line bg-bg-soft">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <p class="eyebrow text-center">{{ __('site.platforms.title') }}</p>
        <ul class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-card border border-line bg-line md:grid-cols-4">
            @foreach (\App\Enums\ProviderSlug::cases() as $slug)
                <li class="bg-bg-soft px-5 py-5 text-center">
                    <p class="text-xl font-semibold text-fg">{{ $slug->displayName() }}</p>
                    <p class="mt-1 text-sm text-muted">{{ __('site.platforms.items.'.$slug->value) }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
