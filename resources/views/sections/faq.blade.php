<section id="faq" class="border-t border-line">
    <div class="mx-auto max-w-3xl px-4 py-24 sm:px-6">
        <x-ui.section-header :eyebrow="__('site.faq.eyebrow')" :title="__('site.faq.title')" />
        <div class="mt-12 border-t border-line">
            @foreach (trans('site.faq.items') as $item)
                <details class="group border-b border-line">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-5 text-lg font-medium text-fg">
                        {{ $item['q'] }}
                        <x-lucide-plus class="size-5 shrink-0 text-muted transition group-open:rotate-45" />
                    </summary>
                    <p class="pb-6 pr-10 text-muted">{{ $item['a'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
