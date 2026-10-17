<section id="why" class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
    <x-ui.section-header :eyebrow="__('site.why.eyebrow')" :title="__('site.why.title')" :subtitle="__('site.why.subtitle')" />
    <div class="mt-12 grid gap-5 md:grid-cols-3">
        @php($icons = ['message-circle', 'chart-column', 'target'])
        @foreach (trans('site.why.points') as $point)
            <x-ui.card class="h-full hover:border-line-strong">
                <x-dynamic-component :component="'lucide-'.$icons[$loop->index]" class="size-6 text-accent" />
                <h3 class="mt-4 text-lg font-semibold">{{ $point['title'] }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-muted">{{ $point['text'] }}</p>
            </x-ui.card>
        @endforeach
    </div>
</section>
