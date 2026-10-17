<section id="how" class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
    <x-ui.section-header :eyebrow="__('site.how.eyebrow')" :title="__('site.how.title')" :subtitle="__('site.how.subtitle')" />
    <ol class="mt-12 grid gap-5 md:grid-cols-3">
        @foreach (trans('site.how.steps') as $step)
            <li>
                <x-ui.card class="h-full hover:border-line-strong">
                    <span class="grid size-9 place-items-center rounded-lg bg-accent/10 text-sm font-semibold text-accent">{{ $loop->iteration }}</span>
                    <h3 class="mt-4 text-lg font-semibold">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted">{{ $step['text'] }}</p>
                </x-ui.card>
            </li>
        @endforeach
    </ol>
</section>
