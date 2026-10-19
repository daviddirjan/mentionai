@php
    // Static illustration only. These numbers are not real measurements.
    $sample = [
        ['name' => 'ChatGPT', 'value' => 33],
        ['name' => 'Gemini', 'value' => 67],
        ['name' => 'Claude', 'value' => 0],
        ['name' => 'Perplexity', 'value' => 100],
    ];
@endphp

<section id="example" class="border-y border-line bg-bg-soft">
    <div class="mx-auto max-w-6xl px-4 py-24 sm:px-6">
        <x-ui.section-header :eyebrow="__('site.example.eyebrow')" :title="__('site.example.title')" :subtitle="__('site.example.subtitle')" />

        <x-ui.card class="mx-auto mt-14 max-w-4xl sm:p-10" data-testid="example-analysis">
            <x-ui.badge tone="warn" class="mb-8"><x-lucide-info class="size-3.5" /> {{ __('site.example.label') }}</x-ui.badge>
            <div class="grid items-center gap-10 md:grid-cols-[auto_1fr]">
                <div class="flex flex-col items-center">
                    <x-score-ring :value="50" :size="170" :label="__('site.example.score')" />
                    <p class="mt-3 font-medium text-fg">{{ __('site.example.company') }}</p>
                </div>
                <div class="space-y-5">
                    @foreach ($sample as $row)
                        <div>
                            <div class="flex justify-between"><span class="font-medium text-fg">{{ $row['name'] }}</span><span class="tabular-nums text-muted">{{ $row['value'] }}%</span></div>
                            <div class="mt-2 h-1 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full bg-fg" style="width: {{ $row['value'] }}%"></div></div>
                        </div>
                    @endforeach
                    <p class="pt-1 text-sm text-muted">{{ __('site.example.competitors') }}: Competitor A, Competitor B, Competitor C</p>
                </div>
            </div>
        </x-ui.card>
    </div>
</section>
