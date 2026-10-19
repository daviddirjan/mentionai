<section id="how" class="border-t border-line">
    <div class="mx-auto max-w-6xl px-4 py-24 sm:px-6">
        <x-ui.section-header :eyebrow="__('site.how.eyebrow')" :title="__('site.how.title')" />
        <ol class="mt-14 grid gap-px overflow-hidden rounded-card border border-line bg-line md:grid-cols-3">
            @foreach (trans('site.how.steps') as $step)
                <li class="bg-bg p-7 sm:p-8">
                    <span class="font-mono text-sm text-muted">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="mt-6 text-xl font-semibold text-fg">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-muted">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
