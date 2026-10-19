@php
    use App\Enums\AnalysisStatus;
    use App\Enums\MentionClassification;
    use App\Enums\ProviderSlug;
    use App\Enums\ResponseStatus;
    use App\Http\Support\Countries;
    use App\Http\Support\SafeMarkdown;

    $analysis = $this->analysis;
    $completedAt = $analysis->completed_at?->locale($locale)->isoFormat('LL');
    $marketName = Countries::options($locale)[$analysis->market] ?? $analysis->market;
    $score = (float) ($analysis->visibility_score ?? 0);
    $hasScore = $analysis->status === AnalysisStatus::Completed && $analysis->successful_tests > 0;
    $maxMentions = max(1, (int) $analysis->competitors->max('mention_count'));
@endphp

<div class="mx-auto max-w-6xl space-y-8 px-4 py-10 sm:px-6 sm:py-14">
    @if ($analysis->is_demo)
        <div class="flex items-center gap-3 rounded-xl border border-warn/40 bg-warn/10 px-4 py-3 text-sm font-medium text-warn" role="alert" data-testid="demo-banner">
            <x-lucide-triangle-alert class="size-5 shrink-0" />
            {{ __('site.results.demo_banner') }}
        </div>
    @endif

    @if ($reused && $completedAt)
        <div class="flex items-center gap-3 rounded-xl border border-accent/30 bg-accent/10 px-4 py-3 text-sm" role="status" data-testid="cached-notice">
            <x-lucide-info class="size-5 shrink-0 text-accent" />
            {{ __('site.results.cached', ['date' => $completedAt]) }}
        </div>
    @endif

    <section aria-labelledby="company-heading">
        <p class="text-xs font-semibold uppercase tracking-widest text-accent">{{ __('site.results.meta_title') }}</p>
        <h1 id="company-heading" class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $analysis->company_name }}</h1>
        <dl class="mt-5 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-subtle">{{ __('site.results.website') }}</dt><dd class="mt-0.5 break-all font-medium">{{ $analysis->domain }}</dd></div>
            <div><dt class="text-subtle">{{ __('site.results.market') }}</dt><dd class="mt-0.5 font-medium">{{ $marketName }}</dd></div>
            @if ($analysis->location)
                <div><dt class="text-subtle">{{ __('site.results.location') }}</dt><dd class="mt-0.5 font-medium">{{ $analysis->location }}</dd></div>
            @endif
            @if ($analysis->industry)
                <div><dt class="text-subtle">{{ __('site.results.industry') }}</dt><dd class="mt-0.5 font-medium">{{ $analysis->industry }}</dd></div>
            @endif
            @if ($completedAt)
                <div><dt class="text-subtle">{{ __('site.results.analyzed_on') }}</dt><dd class="mt-0.5 font-medium">{{ $completedAt }}</dd></div>
            @endif
        </dl>
    </section>

    {{-- In progress: polls the database for the real pipeline status --}}
    @if (! $analysis->isFinished())
        @php($progress = $this->responseProgress)
        <x-ui.card wire:poll.2s data-testid="progress" class="p-6 sm:p-8">
            <div class="flex items-center gap-3">
                <x-lucide-loader-circle class="size-6 animate-spin text-accent" />
                <div>
                    <h2 class="text-lg font-semibold">{{ __('site.results.progress_title') }}</h2>
                    <p class="text-sm text-muted">{{ __('site.results.progress_text') }}</p>
                </div>
            </div>

            <ol class="mt-6 space-y-3">
                @foreach (\App\Livewire\AnalysisResults::PROGRESS_STEPS as $i => $step)
                    @php($current = $this->stepIndex())
                    <li class="flex items-center gap-3 text-sm {{ $i > $current ? 'text-subtle' : ($i === $current ? 'font-medium text-fg' : 'text-muted') }}">
                        @if ($i < $current)
                            <x-lucide-circle-check class="size-5 text-good" />
                        @elseif ($i === $current)
                            <x-lucide-loader-circle class="size-5 animate-spin text-accent" />
                        @else
                            <x-lucide-circle-dashed class="size-5" />
                        @endif
                        {{ __($step->translationKey()) }}
                        @if ($step === AnalysisStatus::QueryingProviders && $i === $current && $progress['total'] > 0)
                            <span class="text-xs text-muted">({{ __('site.results.progress_responses', ['done' => $progress['done'], 'total' => $progress['total']]) }})</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </x-ui.card>

    @elseif ($analysis->status === AnalysisStatus::Failed || ! $hasScore)
        <x-ui.card class="border-bad/30 p-6 sm:p-8" data-testid="failed">
            <div class="flex gap-4">
                <x-lucide-circle-x class="size-6 shrink-0 text-bad" />
                <div>
                    <h2 class="text-lg font-semibold">{{ __('site.results.failed_title') }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ __('site.results.failed_text') }}</p>
                    <x-ui.button :href="route('home').'#analyze'" variant="secondary" class="mt-5">{{ __('site.results.try_again') }}</x-ui.button>
                </div>
            </div>
        </x-ui.card>

    @else
        {{-- Score + providers --}}
        <section class="grid gap-6 lg:grid-cols-[minmax(0,20rem)_1fr]" aria-labelledby="score-heading">
            <x-ui.card class="flex flex-col items-center text-center">
                <h2 id="score-heading" class="mb-4 text-sm font-medium text-muted">{{ __('site.results.score_title') }}</h2>
                <x-score-ring :value="$score" :label="__('site.results.score_label')" />
                <p class="mt-4 font-medium">{{ trans_choice('site.results.successful_tests', $analysis->successful_tests) }}</p>
                @if ($analysis->failed_tests > 0)
                    <p class="mt-1 text-xs text-muted">{{ __('site.results.failed_tests', ['count' => $analysis->failed_tests]) }}</p>
                @endif
                <p class="mt-4 text-xs leading-relaxed text-subtle">{{ __('site.results.disclaimer') }}</p>
            </x-ui.card>

            <div>
                <h2 class="mb-4 text-lg font-semibold">{{ __('site.results.platforms') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2" data-testid="providers">
                    @foreach ($this->providers as $provider)
                        @php($tone = ['recommended' => 'good', 'not_recommended' => 'neutral', 'failed' => 'bad', 'partial' => 'warn', 'not_run' => 'neutral'][$provider['state']])
                        <x-ui.card class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-semibold">{{ $provider['name'] }}</h3>
                                <x-ui.badge :tone="$tone">{{ __('site.results.provider_status.'.$provider['state']) }}</x-ui.badge>
                            </div>
                            @if ($provider['state'] === 'failed' || $provider['state'] === 'not_run')
                                <p class="mt-4 text-sm text-muted">{{ __('site.results.provider_failed_text') }}</p>
                            @else
                                <p class="mt-4 text-3xl font-semibold tracking-tight">{{ rtrim(rtrim(number_format($provider['percentage'], 1), '0'), '.') }}%</p>
                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-2">
                                    <div class="h-full rounded-full bg-gradient-to-r from-accent to-accent-2" style="width: {{ min(100, max(0, $provider['percentage'])) }}%"></div>
                                </div>
                                <p class="mt-3 text-xs text-muted">{{ __('site.results.recommended_of', ['recommended' => $provider['recommended'], 'successful' => $provider['successful']]) }}</p>
                            @endif
                        </x-ui.card>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Competitors --}}
        <section aria-labelledby="competitors-heading">
            <h2 id="competitors-heading" class="mb-4 text-lg font-semibold">{{ __('site.results.competitors') }}</h2>
            <x-ui.card>
                @forelse ($analysis->competitors as $competitor)
                    <div class="{{ $loop->first ? '' : 'mt-4' }}">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium">{{ $competitor->name }}</span>
                            <span class="text-muted">{{ __('site.results.competitor_count', ['count' => $competitor->mention_count]) }}</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-2">
                            <div class="h-full rounded-full bg-gradient-to-r from-accent to-accent-2" style="width: {{ round($competitor->mention_count / $maxMentions * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('site.results.competitors_empty') }}</p>
                @endforelse
            </x-ui.card>
        </section>

        {{-- Questions --}}
        <section aria-labelledby="questions-heading">
            <h2 id="questions-heading" class="mb-4 text-lg font-semibold">{{ __('site.results.questions') }}</h2>
            <div class="space-y-3">
                @forelse ($analysis->questions as $question)
                    <details class="group rounded-card border border-line bg-surface open:bg-surface" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-4 p-5">
                            <span class="font-medium"><span class="mr-2 text-subtle">{{ $loop->iteration }}.</span>{{ $question->question }}</span>
                            <x-lucide-chevron-down class="mt-1 size-5 shrink-0 text-muted transition group-open:rotate-180" />
                        </summary>
                        <div class="space-y-4 border-t border-line p-5">
                            @foreach (ProviderSlug::cases() as $slug)
                                @php($response = $question->responses->firstWhere('provider', $slug->value))
                                @continue(! $response)
                                @php($classification = $response->brandMention?->classification)
                                <div class="rounded-xl border border-line bg-bg-soft p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h4 class="text-sm font-semibold">{{ $slug->displayName() }}</h4>
                                        @if ($response->status === ResponseStatus::Succeeded && $classification)
                                            <x-ui.badge :tone="match ($classification) { MentionClassification::Recommended => 'good', MentionClassification::Mentioned, MentionClassification::CitedOnly => 'accent', MentionClassification::Uncertain => 'warn', default => 'neutral' }">
                                                {{ __($classification->translationKey()) }}
                                            </x-ui.badge>
                                        @endif
                                    </div>

                                    @if ($response->status === ResponseStatus::Succeeded)
                                        <div class="prose-ai mt-3 text-sm leading-relaxed text-muted">{!! SafeMarkdown::render($response->response_text) !!}</div>
                                        @php($citations = collect($response->citations ?? [])->map(fn ($c) => is_array($c) ? $c : ['url' => (string) $c])->filter(fn ($c) => preg_match('#^https?://#i', (string) ($c['url'] ?? ''))))
                                        <div class="mt-3 text-xs text-subtle">
                                            @if ($citations->isNotEmpty())
                                                <p class="mb-1 font-medium text-muted">{{ __('site.results.citations') }}</p>
                                                <ul class="space-y-1">
                                                    @foreach ($citations as $citation)
                                                        <li><a class="break-all text-accent hover:underline" href="{{ $citation['url'] }}" target="_blank" rel="nofollow noopener noreferrer">{{ $citation['title'] ?? $citation['url'] }}</a></li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                {{ __('site.results.no_citations') }}
                                            @endif
                                        </div>
                                    @elseif ($response->status === ResponseStatus::Failed)
                                        <p class="mt-3 text-sm text-bad">{{ __('site.results.answer_failed') }}</p>
                                    @elseif ($response->status === ResponseStatus::Skipped)
                                        <p class="mt-3 text-sm text-muted">{{ __('site.results.answer_skipped') }}</p>
                                    @else
                                        <p class="mt-3 text-sm text-muted">{{ __('site.results.answer_pending') }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <x-ui.card><p class="text-sm text-muted">{{ __('site.results.questions_empty') }}</p></x-ui.card>
                @endforelse
            </div>
        </section>

        {{-- Commercial section --}}
        <section class="relative overflow-hidden rounded-card border border-line bg-surface p-6 sm:p-10" aria-labelledby="commercial-heading">
            <div class="pointer-events-none absolute -left-20 -top-20 size-72 rounded-full bg-accent-2/10 blur-3xl"></div>
            <div class="relative max-w-3xl">
                <h2 id="commercial-heading" class="text-2xl font-semibold tracking-tight sm:text-3xl" data-testid="commercial-title">
                    {{ $score < \App\Livewire\AnalysisResults::LOW_SCORE_THRESHOLD ? __('site.results.low_title') : __('site.results.high_title') }}
                </h2>
                <p class="mt-3 text-muted">{{ __('site.results.commercial_text') }}</p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div>
                        <x-ui.button href="#talk" wire:click="$dispatch('prefill-lead', { topic: 'audit' })">{{ __('site.results.cta_audit') }}</x-ui.button>
                        <p class="mt-1.5 text-xs text-subtle">{{ __('site.results.cta_audit_soon') }}</p>
                    </div>
                    <x-ui.button href="#talk" variant="secondary" class="self-start">{{ __('site.results.cta_expert') }}</x-ui.button>
                </div>
            </div>
        </section>
    @endif

    <section id="talk" class="scroll-mt-24" aria-labelledby="talk-heading">
        <x-ui.card class="p-6 sm:p-8">
            <h2 id="talk-heading" class="text-xl font-semibold tracking-tight">{{ __('site.lead.title') }}</h2>
            <p class="mb-6 mt-1 text-sm text-muted">{{ __('site.lead.subtitle') }}</p>
            <livewire:lead-form :analysis-uuid="$analysis->uuid" :company="$analysis->company_name" :website="$analysis->website_url" :key="'lead-'.$analysis->uuid" />
        </x-ui.card>
    </section>
</div>
