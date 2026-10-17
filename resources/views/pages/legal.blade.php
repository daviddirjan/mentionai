<x-layouts.app :title="__('site.legal.'.$page.'.title')" :description="__('site.legal.'.$page.'.sections.0.text')">
    <article class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <h1 class="text-4xl font-semibold tracking-tight">{{ __('site.legal.'.$page.'.title') }}</h1>

        <div class="mt-6 flex gap-3 rounded-xl border border-warn/40 bg-warn/10 p-4 text-sm text-warn" role="note" data-testid="legal-placeholder">
            <x-lucide-triangle-alert class="mt-0.5 size-5 shrink-0" />
            <p>{{ __('site.legal.placeholder_notice') }}</p>
        </div>

        <div class="mt-10 space-y-8">
            @foreach (trans('site.legal.'.$page.'.sections') as $section)
                <section>
                    <h2 class="text-xl font-semibold tracking-tight">{{ $section['title'] }}</h2>
                    <p class="mt-2 leading-relaxed text-muted">{{ $section['text'] }}</p>
                </section>
            @endforeach
        </div>

        @if ($page === 'privacy')
            <x-ui.button :href="route('contact', ['subject' => 'data_deletion'])" variant="secondary" class="mt-10">{{ __('site.footer.deletion') }}</x-ui.button>
        @endif
    </article>
</x-layouts.app>
