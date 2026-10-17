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
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <x-ui.section-header :eyebrow="__('site.example.eyebrow')" :title="__('site.example.title')" :subtitle="__('site.example.subtitle')" />

        <x-ui.card class="mx-auto mt-12 max-w-4xl p-6 sm:p-8" data-testid="example-analysis">
            <x-ui.badge tone="warn" class="mb-6"><x-lucide-info class="size-3.5" /> {{ __('site.example.label') }}</x-ui.badge>
            <div class="grid items-center gap-8 md:grid-cols-[auto_1fr]">
                <div class="flex flex-col items-center">
                    <x-score-ring :value="50" :size="160" :label="__('site.example.score')" />
                    <p class="mt-3 text-sm font-medium">{{ __('site.example.company') }}</p>
                </div>
                <div class="space-y-4">
                    @foreach ($sample as $row)
                        <div>
                            <div class="flex justify-between text-sm"><span class="font-medium">{{ $row['name'] }}</span><span class="text-muted">{{ $row['value'] }}%</span></div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full bg-gradient-to-r from-accent to-accent-2" style="width: {{ $row['value'] }}%"></div></div>
                        </div>
                    @endforeach
                    <p class="pt-2 text-xs text-subtle">{{ __('site.example.competitors') }}: Competitor A, Competitor B, Competitor C</p>
                </div>
            </div>
        </x-ui.card>
    </div>
</section>
