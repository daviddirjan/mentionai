@php
    use App\Enums\AnalysisStatus;
    use App\Enums\CheckStatus;
    use App\Enums\MentionClassification;
    use App\Enums\ProviderSlug;
    use App\Enums\ResponseStatus;
    use App\Http\Support\AuditText;
    use App\Http\Support\Countries;
    use App\Http\Support\SafeMarkdown;

    $analysis = $this->analysis;
    $isAudit = $analysis->isAudit();
    $completedAt = $analysis->completed_at?->locale($locale)->isoFormat('LL');
    $marketName = Countries::options($locale)[$analysis->market] ?? $analysis->market;
    $score = (float) ($analysis->visibility_score ?? 0);
    $hasScore = $analysis->status === AnalysisStatus::Completed && $analysis->successful_tests > 0;
    $maxMentions = max(1, (int) $analysis->competitors->max('mention_count'));
    $report = $hasScore ? $this->report : null;
    $statusTone = ['pass' => 'bg-good', 'warn' => 'bg-warn', 'fail' => 'bg-bad', 'info' => 'bg-subtle'];
@endphp

<div class="mx-auto max-w-6xl space-y-12 px-4 py-12 sm:px-6 sm:py-16">
    @if ($analysis->is_demo)
        <div class="flex items-center gap-3 rounded-xl border border-warn/40 bg-warn/10 px-4 py-3 text-sm font-medium text-warn" role="alert" data-testid="demo-banner">
            <x-lucide-triangle-alert class="size-5 shrink-0" />
            {{ __('site.results.demo_banner') }}
        </div>
    @endif

    @if ($reused && $completedAt)
        <div class="flex items-center gap-3 rounded-xl border border-line-strong bg-surface-2 px-4 py-3 text-sm" role="status" data-testid="cached-notice">
            <x-lucide-info class="size-5 shrink-0 text-muted" />
            {{ __('site.results.cached', ['date' => $completedAt]) }}
        </div>
    @endif

    <section aria-labelledby="company-heading" class="border-b border-line pb-10">
        <div class="flex flex-wrap items-center gap-3">
            <p class="eyebrow inline-flex items-center gap-2"><span class="signal-dot" aria-hidden="true"></span>{{ __('site.results.meta_title') }}</p>
            @if ($isAudit)
                <x-ui.badge tone="accent" data-testid="audit-badge">{{ __('site.results.audit.badge') }}</x-ui.badge>
            @endif
        </div>
        <h1 id="company-heading" class="mt-4 text-4xl font-semibold text-fg sm:text-5xl">{{ $analysis->company_name }}</h1>
        <dl class="mt-6 grid gap-x-10 gap-y-4 sm:grid-cols-2 lg:grid-cols-5">
            <div><dt class="text-sm text-muted">{{ __('site.results.website') }}</dt><dd class="mt-0.5 break-all font-medium text-fg">{{ $analysis->domain }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('site.results.market') }}</dt><dd class="mt-0.5 font-medium text-fg">{{ $marketName }}</dd></div>
            @if ($analysis->location)
                <div><dt class="text-sm text-muted">{{ __('site.results.location') }}</dt><dd class="mt-0.5 font-medium text-fg">{{ $analysis->location }}</dd></div>
            @endif
            @if ($analysis->industry)
                <div><dt class="text-sm text-muted">{{ __('site.results.industry') }}</dt><dd class="mt-0.5 font-medium text-fg">{{ $analysis->industry }}</dd></div>
            @endif
            @if ($completedAt)
                <div><dt class="text-sm text-muted">{{ __('site.results.analyzed_on') }}</dt><dd class="mt-0.5 font-medium text-fg">{{ $completedAt }}</dd></div>
            @endif
        </dl>
    </section>

    {{-- In progress: polls the database for the real pipeline status --}}
    @if (! $analysis->isFinished())
        @php($progress = $this->responseProgress)
        @php($current = $this->stepIndex())
        <x-ui.card wire:poll.2s data-testid="progress" class="sm:p-9">
            <div class="flex items-center gap-3">
                <x-lucide-loader-circle class="size-6 animate-spin text-fg" />
                <div>
                    <h2 class="text-xl font-semibold text-fg">{{ $isAudit ? __('site.results.audit.progress_title') : __('site.results.progress_title') }}</h2>
                    <p class="text-muted">{{ $isAudit ? __('site.results.audit.progress_text') : __('site.results.progress_text') }}</p>
                </div>
            </div>

            <ol class="mt-8 space-y-3.5">
                @foreach ($this->progressSteps() as $i => $step)
                    <li class="flex items-center gap-3 {{ $i > $current ? 'text-subtle' : ($i === $current ? 'font-medium text-fg' : 'text-muted') }}">
                        @if ($i < $current)
                            <x-lucide-circle-check class="size-5 text-good" />
                        @elseif ($i === $current)
                            <x-lucide-loader-circle class="size-5 animate-spin text-fg" />
                        @else
                            <x-lucide-circle-dashed class="size-5" />
                        @endif
                        {{ __($step->translationKey()) }}
                        @if ($step === AnalysisStatus::QueryingProviders && $i === $current && $progress['total'] > 0)
                            <span class="text-sm text-muted">({{ __('site.results.progress_responses', ['done' => $progress['done'], 'total' => $progress['total']]) }})</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </x-ui.card>

    @elseif ($analysis->status === AnalysisStatus::Failed || ! $hasScore)
        <x-ui.card class="border-bad/30 sm:p-9" data-testid="failed">
            <div class="flex gap-4">
                <x-lucide-circle-x class="size-6 shrink-0 text-bad" />
                <div>
                    <h2 class="text-xl font-semibold text-fg">{{ __('site.results.failed_title') }}</h2>
                    <p class="mt-1 text-muted">{{ __('site.results.failed_text') }}</p>
                    <x-ui.button :href="route('home').'#analyze'" variant="secondary" class="mt-5">{{ __('site.results.try_again') }}</x-ui.button>
                </div>
            </div>
        </x-ui.card>

    @else
        {{-- Audit verdict + PDF --}}
        @if ($report)
            <section class="grid gap-6 lg:grid-cols-[1fr_20rem]" aria-labelledby="verdict-heading" data-testid="audit-verdict">
                <div class="rounded-card bg-bg-soft p-7 sm:p-9">
                    <h2 id="verdict-heading" class="eyebrow">{{ __('site.results.audit.verdict') }}</h2>
                    <p class="mt-4 text-xl leading-relaxed text-fg sm:text-2xl">{{ $report->narrative->verdict }}</p>
                    @if ($report->narrative->summary)
                        <ul class="mt-6 space-y-2 border-t border-line pt-6">
                            @foreach ($report->narrative->summary as $item)
                                <li class="flex items-start gap-2.5 text-fg"><span class="signal-dot mt-2.5 shrink-0" aria-hidden="true"></span>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <x-ui.card class="flex flex-col justify-center" data-testid="pdf-download">
                    <x-lucide-file-text class="size-7 text-fg" />
                    @if (! $analysis->hasReport())
                        <div wire:poll.5s data-testid="pdf-generating">
                            <p class="mt-4 font-semibold text-fg">{{ __('site.results.audit.download') }}</p>
                            <p class="mt-1 inline-flex items-start gap-2 text-sm text-muted"><x-lucide-loader-circle class="mt-0.5 size-4 shrink-0 animate-spin" />{{ __('site.results.audit.generating') }}</p>
                        </div>
                    @elseif ($this->hasReportAccess())
                        <x-ui.button :href="route('report.download', ['analysis' => $analysis->uuid])" size="lg" class="mt-5 w-full" data-testid="pdf-link">
                            <x-lucide-download class="size-5" /> {{ __('site.results.audit.download') }}
                        </x-ui.button>
                    @else
                        <p class="mt-4 font-semibold text-fg">{{ __('site.results.audit.unlock_title') }}</p>
                        <p class="mb-4 mt-1 text-sm text-muted">{{ __('site.results.audit.unlock_text') }}</p>
                        <div data-testid="pdf-unlock">
                            <livewire:lead-form :analysis-uuid="$analysis->uuid" mode="unlock" :key="'unlock-'.$analysis->uuid" />
                        </div>
                    @endif
                </x-ui.card>
            </section>
        @endif

        {{-- Score + providers --}}
        <section class="grid gap-6 lg:grid-cols-[minmax(0,20rem)_1fr]" aria-labelledby="score-heading">
            <x-ui.card class="flex flex-col items-center text-center">
                <h2 id="score-heading" class="mb-5 text-sm font-medium text-muted">{{ __('site.results.score_title') }}</h2>
                <x-score-ring :value="$score" :label="__('site.results.score_label')" />
                <p class="mt-5 font-medium text-fg">{{ trans_choice('site.results.successful_tests', $analysis->successful_tests) }}</p>
                @if ($analysis->failed_tests > 0)
                    <p class="mt-1 text-sm text-muted">{{ __('site.results.failed_tests', ['count' => $analysis->failed_tests]) }}</p>
                @endif
                <p class="mt-5 text-xs leading-relaxed text-muted">{{ __('site.results.disclaimer') }}</p>
            </x-ui.card>

            <div>
                <h2 class="mb-4 text-xl font-semibold text-fg">{{ __('site.results.platforms') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2" data-testid="providers">
                    @foreach ($this->providers as $provider)
                        @php($tone = ['recommended' => 'good', 'not_recommended' => 'neutral', 'failed' => 'bad', 'partial' => 'warn', 'not_run' => 'neutral'][$provider['state']])
                        <x-ui.card class="p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-lg font-semibold text-fg">{{ $provider['name'] }}</h3>
                                <x-ui.badge :tone="$tone">{{ __('site.results.provider_status.'.$provider['state']) }}</x-ui.badge>
                            </div>
                            @if ($provider['state'] === 'failed' || $provider['state'] === 'not_run')
                                <p class="mt-4 text-sm text-muted">{{ __('site.results.provider_failed_text') }}</p>
                            @else
                                <p class="mt-4 text-4xl font-semibold tracking-tight text-fg">{{ rtrim(rtrim(number_format($provider['percentage'], 1), '0'), '.') }}%</p>
                                <div class="mt-3 h-1 overflow-hidden rounded-full bg-line">
                                    <div class="h-full rounded-full bg-fg" style="width: {{ min(100, max(0, $provider['percentage'])) }}%"></div>
                                </div>
                                <p class="mt-3 text-sm text-muted">{{ __('site.results.recommended_of', ['recommended' => $provider['recommended'], 'successful' => $provider['successful']]) }}</p>
                            @endif
                        </x-ui.card>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Free check → GEO audit offer --}}
        @if (! $isAudit && $this->auditEnabled())
            <section class="rounded-card bg-accent p-7 text-accent-fg sm:p-10" aria-labelledby="audit-offer-heading" data-testid="audit-offer">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] opacity-80"><span class="signal-dot mr-2" aria-hidden="true"></span>{{ __('site.results.audit.offer_eyebrow') }}</p>
                <h2 id="audit-offer-heading" class="mt-3 text-3xl font-semibold sm:text-4xl">{{ __('site.results.audit.offer_title') }}</h2>
                <p class="mt-3 max-w-2xl text-lg opacity-85">{{ __('site.results.audit.offer_text', ['tests' => $this->auditTests()]) }}</p>
                <div class="mt-7 rounded-xl bg-surface p-5 text-fg sm:p-6">
                    <livewire:lead-form :analysis-uuid="$analysis->uuid" :company="$analysis->company_name" :website="$analysis->website_url" mode="audit" :key="'audit-'.$analysis->uuid" />
                </div>
            </section>
        @endif

        @if ($report)
            {{-- Visibility by question type --}}
            @if ($report->categories)
                <section aria-labelledby="categories-heading">
                    <h2 id="categories-heading" class="mb-4 text-xl font-semibold text-fg">{{ __('site.results.audit.categories') }}</h2>
                    <x-ui.card class="p-0 sm:p-0">
                        <ul class="divide-y divide-line">
                            @foreach ($report->categories as $category => $row)
                                <li class="grid grid-cols-[1fr_auto] items-center gap-x-6 gap-y-2 px-5 py-4 sm:grid-cols-[14rem_1fr_6rem] sm:px-7">
                                    <span class="font-medium text-fg">{{ __('audit.category.'.$category) }}</span>
                                    <span class="order-last col-span-2 h-1 overflow-hidden rounded-full bg-line sm:order-none sm:col-span-1"><span class="block h-full rounded-full bg-fg" style="width: {{ min(100, max(0, (float) $row['percentage'])) }}%"></span></span>
                                    <span class="text-right tabular-nums text-muted">{{ $row['recommended'] }}/{{ $row['successful'] }} · <span class="font-medium text-fg">{{ AuditText::percent($row['percentage'], 'en') }}</span></span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                </section>
            @endif

            {{-- Technical findings --}}
            <section aria-labelledby="findings-heading" data-testid="audit-findings">
                <h2 id="findings-heading" class="text-xl font-semibold text-fg">{{ __('site.results.audit.findings') }}</h2>
                <p class="mb-4 mt-1 text-muted">{{ __('site.results.audit.findings_text', ['domain' => $analysis->domain]) }}</p>
                <x-ui.card class="overflow-x-auto p-0 sm:p-0">
                    <table class="w-full min-w-[40rem] text-left">
                        <thead>
                            <tr class="border-b border-line-strong text-xs uppercase tracking-wider text-muted">
                                <th scope="col" class="px-5 py-3 font-medium sm:px-7">{{ __('audit.pdf.findings.finding') }}</th>
                                <th scope="col" class="px-5 py-3 font-medium">{{ __('audit.pdf.findings.evidence') }}</th>
                                <th scope="col" class="px-5 py-3 font-medium sm:pr-7">{{ __('audit.pdf.findings.impact') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($report->checks as $check)
                                <tr class="align-top">
                                    <td class="px-5 py-4 sm:px-7">
                                        <span class="inline-flex items-start gap-2.5 font-medium text-fg">
                                            <span class="mt-2 inline-block size-2 shrink-0 rounded-full {{ $statusTone[$check->status->value] }}" aria-hidden="true"></span>
                                            <span>{{ AuditText::check($check, 'title', $analysis, $locale) }}<span class="block text-xs font-normal text-muted">{{ __('audit.status.'.$check->status->value) }}</span></span>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 font-mono text-[0.8rem] leading-relaxed text-fg">{{ $check->evidence }}</td>
                                    <td class="px-5 py-4 text-sm text-muted sm:pr-7">{{ AuditText::check($check, 'impact', $analysis, $locale) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-7 py-5 text-muted">{{ __('audit.pdf.findings.empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-ui.card>
            </section>

            {{-- Priorities --}}
            <section aria-labelledby="priorities-heading" data-testid="audit-priorities">
                <h2 id="priorities-heading" class="mb-4 text-xl font-semibold text-fg">{{ __('site.results.audit.priorities') }}</h2>
                <div class="space-y-3">
                    @forelse ($report->recommendations as $rec)
                        <x-ui.card class="grid gap-4 p-5 sm:grid-cols-[7rem_1fr] sm:p-6">
                            <div>
                                <p class="font-mono text-lg font-semibold text-fg">{{ $rec->priority->label() }}</p>
                                <p class="text-sm text-muted">{{ __('audit.pdf.priorities.days', ['window' => str_replace('-', '–', $rec->window)]) }}</p>
                            </div>
                            <div>
                                <p class="font-medium text-fg">{{ AuditText::rec($rec, 'action', $analysis, $locale) }}</p>
                                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                    <div><dt class="text-xs uppercase tracking-wider text-muted">{{ __('audit.pdf.priorities.acceptance') }}</dt><dd class="mt-1 text-fg">{{ AuditText::rec($rec, 'acceptance', $analysis, $locale) }}</dd></div>
                                    <div><dt class="text-xs uppercase tracking-wider text-muted">{{ __('audit.pdf.priorities.why') }}</dt><dd class="mt-1 text-fg">{{ AuditText::rec($rec, 'why', $analysis, $locale) }}</dd></div>
                                </dl>
                            </div>
                        </x-ui.card>
                    @empty
                        <x-ui.card><p class="text-muted">{{ __('audit.pdf.priorities.empty') }}</p></x-ui.card>
                    @endforelse
                </div>
            </section>

            {{-- Before / after --}}
            @if ($report->history)
                @php($history = $report->history)
                <section aria-labelledby="history-heading" data-testid="audit-history">
                    <h2 id="history-heading" class="text-xl font-semibold text-fg">{{ __('site.results.audit.history') }}</h2>
                    <p class="mb-4 mt-1 text-muted">{{ __('audit.pdf.history.intro', ['date' => $history->previousDate->locale($locale)->isoFormat('LL')]) }}</p>
                    <x-ui.card class="grid gap-6 sm:grid-cols-3">
                        <div>
                            <p class="text-sm text-muted">{{ __('audit.pdf.history.score') }}</p>
                            <p class="mt-1 text-3xl font-semibold text-fg">{{ $history->scoreBefore === null ? '—' : AuditText::percent($history->scoreBefore, 'en') }} <span class="text-muted">→</span> {{ $history->scoreAfter === null ? '—' : AuditText::percent($history->scoreAfter, 'en') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted">{{ __('audit.pdf.history.fixed') }}</p>
                            <p class="mt-1 text-fg">{{ $history->checksFixed ? collect($history->checksFixed)->map(fn ($k) => __("audit.check.{$k}.title", ['bot' => $k]))->implode(', ') : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted">{{ __('audit.pdf.history.gained') }}</p>
                            <p class="mt-1 text-fg">{{ $history->competitorsGained ? implode(', ', $history->competitorsGained) : '—' }}</p>
                        </div>
                    </x-ui.card>
                </section>
            @endif
        @endif

        {{-- Competitors --}}
        <section aria-labelledby="competitors-heading">
            <h2 id="competitors-heading" class="mb-4 text-xl font-semibold text-fg">{{ __('site.results.competitors') }}</h2>
            <x-ui.card>
                @forelse ($analysis->competitors as $competitor)
                    <div class="{{ $loop->first ? '' : 'mt-5' }}">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium text-fg">{{ $competitor->name }}</span>
                            <span class="text-sm tabular-nums text-muted">{{ __('site.results.competitor_count', ['count' => $competitor->mention_count]) }}</span>
                        </div>
                        <div class="mt-2 h-1 overflow-hidden rounded-full bg-line">
                            <div class="h-full rounded-full bg-fg" style="width: {{ round($competitor->mention_count / $maxMentions * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted">{{ __('site.results.competitors_empty') }}</p>
                @endforelse
            </x-ui.card>
        </section>

        {{-- Questions --}}
        <section aria-labelledby="questions-heading">
            <h2 id="questions-heading" class="mb-4 text-xl font-semibold text-fg">{{ __('site.results.questions') }}</h2>
            <div class="space-y-3">
                @forelse ($analysis->questions as $question)
                    <details class="group rounded-card border border-line bg-surface" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-4 p-5 sm:px-6">
                            <span class="font-medium text-fg"><span class="mr-2 font-mono text-sm text-muted">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $question->question }}</span>
                            <x-lucide-chevron-down class="mt-1 size-5 shrink-0 text-muted transition group-open:rotate-180" />
                        </summary>
                        <div class="space-y-4 border-t border-line p-5 sm:px-6">
                            @foreach (ProviderSlug::cases() as $slug)
                                @php($response = $question->responses->firstWhere('provider', $slug->value))
                                @continue(! $response)
                                @php($classification = $response->brandMention?->classification)
                                <div class="rounded-xl border border-line bg-bg p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h4 class="font-semibold text-fg">{{ $slug->displayName() }}</h4>
                                        @if ($response->status === ResponseStatus::Succeeded && $classification)
                                            <x-ui.badge :tone="match ($classification) { MentionClassification::Recommended => 'good', MentionClassification::Mentioned, MentionClassification::CitedOnly => 'accent', MentionClassification::Uncertain => 'warn', default => 'neutral' }">
                                                {{ __($classification->translationKey()) }}
                                            </x-ui.badge>
                                        @endif
                                    </div>

                                    @if ($response->status === ResponseStatus::Succeeded)
                                        <div class="prose-ai mt-3 text-[0.95rem] leading-relaxed text-muted">{!! SafeMarkdown::render($response->response_text) !!}</div>
                                        @php($citations = collect($response->citations ?? [])->map(fn ($c) => is_array($c) ? $c : ['url' => (string) $c])->filter(fn ($c) => preg_match('#^https?://#i', (string) ($c['url'] ?? ''))))
                                        <div class="mt-3 text-sm text-muted">
                                            @if ($citations->isNotEmpty())
                                                <p class="mb-1 font-medium text-fg">{{ __('site.results.citations') }}</p>
                                                <ul class="space-y-1">
                                                    @foreach ($citations as $citation)
                                                        <li><a class="break-all underline decoration-signal underline-offset-2 hover:text-fg" href="{{ $citation['url'] }}" target="_blank" rel="nofollow noopener noreferrer">{{ $citation['title'] ?? $citation['url'] }}</a></li>
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
                    <x-ui.card><p class="text-muted">{{ __('site.results.questions_empty') }}</p></x-ui.card>
                @endforelse
            </div>
        </section>

        {{-- Commercial section --}}
        <section class="border-t border-line pt-12" aria-labelledby="commercial-heading">
            <div class="max-w-3xl">
                <h2 id="commercial-heading" class="text-2xl font-semibold text-fg sm:text-3xl" data-testid="commercial-title">
                    {{ $score < \App\Livewire\AnalysisResults::LOW_SCORE_THRESHOLD ? __('site.results.low_title') : __('site.results.high_title') }}
                </h2>
                <p class="mt-3 text-muted">{{ __('site.results.commercial_text') }}</p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                    @if (! $isAudit && $this->auditEnabled())
                        <x-ui.button href="#audit-offer-heading">{{ __('site.results.cta_audit') }}</x-ui.button>
                    @endif
                    <x-ui.button href="#talk" variant="secondary" wire:click="$dispatch('prefill-lead', { topic: 'audit' })">{{ __('site.results.cta_expert') }}</x-ui.button>
                </div>
            </div>
        </section>
    @endif

    <section id="talk" class="scroll-mt-24" aria-labelledby="talk-heading">
        <x-ui.card class="sm:p-9">
            <h2 id="talk-heading" class="text-2xl font-semibold text-fg">{{ __('site.lead.title') }}</h2>
            <p class="mb-6 mt-1 text-muted">{{ __('site.lead.subtitle') }}</p>
            <livewire:lead-form :analysis-uuid="$analysis->uuid" :company="$analysis->company_name" :website="$analysis->website_url" :key="'lead-'.$analysis->uuid" />
        </x-ui.card>
    </section>
</div>
