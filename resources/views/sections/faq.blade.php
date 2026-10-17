<section id="faq" class="mx-auto max-w-3xl px-4 py-20 sm:px-6">
    <x-ui.section-header :eyebrow="__('site.faq.eyebrow')" :title="__('site.faq.title')" />
    <div class="mt-12 space-y-3">
        @foreach (trans('site.faq.items') as $item)
            <details class="group rounded-card border border-line bg-surface">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 font-medium">
                    {{ $item['q'] }}
                    <x-lucide-chevron-down class="size-5 shrink-0 text-muted transition group-open:rotate-180" />
                </summary>
                <p class="px-5 pb-5 text-sm leading-relaxed text-muted">{{ $item['a'] }}</p>
            </details>
        @endforeach
    </div>
</section>
