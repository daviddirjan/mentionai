<section id="features" class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
    <x-ui.section-header :eyebrow="__('site.features.eyebrow')" :title="__('site.features.title')" />
    <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @php($icons = ['layers', 'eye', 'shield-check', 'users', 'search', 'languages'])
        @foreach (trans('site.features.items') as $item)
            <x-ui.card class="h-full hover:border-line-strong">
                <span class="grid size-10 place-items-center rounded-xl bg-accent/10 text-accent"><x-dynamic-component :component="'lucide-'.$icons[$loop->index]" class="size-5" /></span>
                <h3 class="mt-4 font-semibold">{{ $item['title'] }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-muted">{{ $item['text'] }}</p>
            </x-ui.card>
        @endforeach
    </div>
</section>
