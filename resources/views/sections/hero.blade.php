<section class="relative">
    <div class="mx-auto grid max-w-6xl items-start gap-12 px-4 pb-20 pt-14 sm:px-6 sm:pt-20 lg:grid-cols-[1.05fr_1fr] lg:gap-16">
        <div>
            <p class="eyebrow inline-flex items-center gap-2"><span class="signal-dot" aria-hidden="true"></span>{{ __('site.hero.badge') }}</p>
            <h1 class="mt-5 text-[2.6rem] font-semibold leading-[1.05] text-fg sm:text-6xl">{{ __('site.hero.title') }}</h1>
            <p class="mt-6 max-w-xl text-lg text-muted sm:text-xl">{{ __('site.hero.description') }}</p>

            <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-[0.95rem] text-muted">
                @foreach (trans('site.hero.points') as $point)
                    <li class="inline-flex items-center gap-2"><x-lucide-check class="size-4 text-good" /> {{ $point }}</li>
                @endforeach
            </ul>
        </div>

        <div>
            <livewire:analysis-form />
        </div>
    </div>
</section>
