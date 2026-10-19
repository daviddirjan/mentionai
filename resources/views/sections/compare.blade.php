{{-- Product capability comparison; no third-party claims or statistics. --}}
<section id="compare" class="mx-auto max-w-5xl px-4 py-24 sm:px-6">
    <x-ui.section-header :eyebrow="__('site.compare.eyebrow')" :title="__('site.compare.title')" :subtitle="__('site.compare.subtitle')" />

    {{-- Desktop: table --}}
    <div class="mt-14 hidden overflow-hidden rounded-card border border-line bg-surface md:block">
        <table class="w-full text-left" data-testid="compare-table">
            <caption class="sr-only">{{ __('site.compare.title') }}</caption>
            <thead>
                <tr class="border-b border-line">
                    <th scope="col" class="w-[34%] px-7 py-5 text-sm font-medium text-muted"><span class="sr-only">{{ __('site.compare.col_feature') }}</span></th>
                    <th scope="col" class="w-[31%] px-7 py-5 text-sm font-medium text-muted">{{ __('site.compare.col_chatgpt') }}</th>
                    <th scope="col" class="w-[35%] bg-surface-2 px-7 py-5 text-sm font-semibold text-fg">
                        <span class="inline-flex items-center gap-2"><span class="signal-dot" aria-hidden="true"></span>{{ __('site.compare.col_mentionai') }}</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach (trans('site.compare.rows') as $row)
                    <tr @class(['border-b border-line' => ! $loop->last])>
                        <th scope="row" class="px-7 py-5 align-top font-semibold text-fg">{{ $row['feature'] }}</th>
                        <td class="px-7 py-5 align-top text-muted">
                            <span class="inline-flex items-start gap-2"><x-lucide-minus class="mt-1 size-4 shrink-0 text-subtle" />{{ $row['chatgpt'] }}</span>
                        </td>
                        <td class="bg-surface-2 px-7 py-5 align-top font-medium text-fg">
                            <span class="inline-flex items-start gap-2"><x-lucide-check class="mt-1 size-4 shrink-0 text-good" />{{ $row['mentionai'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Mobile: stacked cards --}}
    <ul class="mt-10 space-y-3 md:hidden">
        @foreach (trans('site.compare.rows') as $row)
            <li class="rounded-card border border-line bg-surface p-5">
                <p class="font-semibold text-fg">{{ $row['feature'] }}</p>
                <dl class="mt-3 space-y-2 text-[0.95rem]">
                    <div class="flex items-start gap-2 text-muted">
                        <x-lucide-minus class="mt-1 size-4 shrink-0 text-subtle" />
                        <div><dt class="text-xs uppercase tracking-wider text-subtle">{{ __('site.compare.col_chatgpt') }}</dt><dd>{{ $row['chatgpt'] }}</dd></div>
                    </div>
                    <div class="flex items-start gap-2 text-fg">
                        <x-lucide-check class="mt-1 size-4 shrink-0 text-good" />
                        <div><dt class="text-xs uppercase tracking-wider text-muted">{{ __('site.compare.col_mentionai') }}</dt><dd class="font-medium">{{ $row['mentionai'] }}</dd></div>
                    </div>
                </dl>
            </li>
        @endforeach
    </ul>

    <div class="mt-10 text-center">
        <x-ui.button href="#analyze" size="lg">{{ __('site.compare.cta') }} <x-lucide-arrow-right class="size-5" /></x-ui.button>
    </div>
</section>
