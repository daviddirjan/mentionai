@php
    use App\Enums\CheckStatus;
    use App\Http\Support\AuditText;

    /** @var \App\Data\AuditReport $report */
    $a = $report->analysis;
    $lang = in_array($a->language, config('mentionai.analysis_languages'), true) ? $a->language : 'en';
    $t = fn (string $key, array $params = []) => __('audit.pdf.'.$key, $params, $lang);
    $pct = fn ($value) => AuditText::percent($value === null ? null : (float) $value, $lang);

    $date = ($a->completed_at ?? $report->generatedAt)->copy()->locale($lang)->isoFormat('LL');
    $market = AuditText::marketName($a->market, $lang);
    $languageName = __('site.languages.'.$a->language, [], $lang);
    $providerNames = collect($report->providers)->pluck('name', 'slug')->all();
    $questionCount = count($report->questions);
    $providerCount = count($report->providers);
    $actionChecks = array_values(array_filter($report->checks, fn ($c) => $c->status->needsAction()));
    $passedChecks = count(array_filter($report->checks, fn ($c) => $c->status === CheckStatus::Pass));
    $recommendingProviders = count(array_filter($report->providers, fn ($p) => $p['recommended'] > 0));
    $statusClass = ['pass' => 'pass', 'warn' => 'warn', 'fail' => 'fail', 'info' => 'info'];
    $classTone = ['recommended' => 'pass', 'mentioned' => 'ink', 'cited_only' => 'ink', 'uncertain' => 'warn', 'not_mentioned' => 'muted'];
    $narrative = $report->narrative;
    $hasModel = $narrative->h1 || $narrative->title || $narrative->metaDescription;
    $n = 0;
    $num = function () use (&$n) { $n++; return str_pad((string) $n, 2, '0', STR_PAD_LEFT); };
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<title>{{ $t('footer_title', ['domain' => $a->domain]) }}</title>
<style>
    @font-face { font-family: 'Afacad'; font-weight: bold; src: url("{{ resource_path('fonts/Afacad-Bold.ttf') }}") format('truetype'); }
    @font-face { font-family: 'Afacad SemiBold'; font-weight: normal; src: url("{{ resource_path('fonts/Afacad-SemiBold.ttf') }}") format('truetype'); }
    @font-face { font-family: 'Inter'; font-weight: normal; src: url("{{ resource_path('fonts/Inter-Regular.ttf') }}") format('truetype'); }
    {{-- Bold body text uses the Afacad SemiBold cut: one font file less keeps dompdf's memory in check. --}}
    @font-face { font-family: 'Inter'; font-weight: bold; src: url("{{ resource_path('fonts/Afacad-SemiBold.ttf') }}") format('truetype'); }
    @font-face { font-family: 'JetBrains Mono'; font-weight: normal; src: url("{{ resource_path('fonts/JetBrainsMono-Regular.ttf') }}") format('truetype'); }

    @page { margin: 20mm 19mm 22mm 19mm; }

    body { font-family: 'Inter', DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.42; color: #1b3030; }
    h1, h2, h3 { font-weight: normal; margin: 0; }
    p { margin: 0 0 6pt; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }

    .footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 8mm; border-top: 0.6pt solid #d9dedc; padding-top: 2.5mm; font-size: 7.5pt; color: #5b6b6b; }
    .footer td { padding: 0; }
    .page-number:after { content: counter(page); }

    .mono { font-family: 'JetBrains Mono', DejaVu Sans Mono, monospace; }
    .muted { color: #5b6b6b; }
    .small { font-size: 8pt; }
    .eyebrow { font-family: 'JetBrains Mono', monospace; font-size: 7.5pt; letter-spacing: 1.2pt; text-transform: uppercase; color: #5b6b6b; }
    .label { font-size: 7pt; letter-spacing: 1pt; text-transform: uppercase; color: #5b6b6b; font-weight: bold; }

    .cover-domain { font-family: 'Afacad'; font-weight: bold; font-size: 36pt; line-height: 1.05; color: #1b3030; margin-top: 10pt; }
    .cover-market { font-family: 'Afacad SemiBold'; font-size: 15pt; color: #2d4a4a; margin-top: 4pt; }
    .demo-tag { display: inline-block; font-family: 'JetBrains Mono', monospace; font-size: 7.5pt; letter-spacing: 1pt; color: #8a5d06; background: #fbf3e2; border: 0.6pt solid #e2c27e; padding: 2pt 6pt; }

    .rule { border-top: 0.9pt solid #1b3030; margin: 16pt 0 0; height: 0; }
    .meta { margin-top: 10pt; border-bottom: 0.6pt solid #d9dedc; }
    .meta td { padding: 2.5pt 0; }
    .meta td.k { width: 26%; color: #5b6b6b; }
    .meta-last td { padding-bottom: 10pt; }

    .verdict { background: #f2f0eb; padding: 14pt 16pt 15pt; margin-top: 18pt; }
    .verdict-text { font-family: 'Afacad SemiBold'; font-size: 14pt; line-height: 1.12; color: #162626; margin-top: 6pt; }

    .section { margin-top: 22pt; }
    .section-head { border-top: 0.9pt solid #1b3030; margin-bottom: 8pt; }
    .section-head td { padding-top: 10pt; vertical-align: bottom; }
    .section-num { width: 22pt; font-family: 'JetBrains Mono', monospace; font-size: 8.5pt; color: #8a9696; padding-bottom: 2pt; }
    .section-head h2 { font-family: 'Afacad SemiBold'; font-size: 18pt; line-height: 1.15; color: #1b3030; }
    h3 { font-family: 'Inter'; font-weight: bold; font-size: 9.5pt; margin: 10pt 0 4pt; color: #1b3030; }

    .kpis td { width: 25%; padding: 10pt 10pt 10pt 0; border-bottom: 0.6pt solid #d9dedc; }
    .kpi-value { font-family: 'Afacad'; font-weight: bold; font-size: 22pt; line-height: 1.1; color: #1b3030; }
    .kpi-label { font-size: 7.5pt; color: #5b6b6b; margin-top: 2pt; }

    ul.list { margin: 4pt 0 0; padding-left: 12pt; }
    ul.list li { margin-bottom: 4pt; }

    .grid th { text-align: left; font-size: 7pt; letter-spacing: 0.9pt; text-transform: uppercase; color: #5b6b6b; font-weight: bold; padding: 0 8pt 5pt 0; border-bottom: 0.9pt solid #1b3030; }
    .grid td { padding: 6pt 8pt 6pt 0; border-bottom: 0.6pt solid #d9dedc; }
    .grid tr { page-break-inside: avoid; }
    .num { text-align: right; white-space: nowrap; }
    .grid th.num { text-align: right; }
    .evidence { font-family: 'JetBrains Mono', monospace; font-size: 7.6pt; line-height: 1.5; color: #162626; }

    .dot { display: inline-block; width: 5pt; height: 5pt; border-radius: 2.5pt; margin-right: 4pt; }
    .bg-pass { background: #21a86b; } .bg-warn { background: #c98a12; } .bg-fail { background: #c0392b; } .bg-info { background: #8a9696; }
    .t-pass { color: #21a86b; } .t-warn { color: #c98a12; } .t-fail { color: #c0392b; } .t-info, .t-muted { color: #5b6b6b; } .t-ink { color: #2d4a4a; }
    .tag { font-size: 7.5pt; font-weight: bold; }

    .bar { height: 3pt; background: #e7ebe9; }
    .bar-fill { height: 3pt; background: #1b3030; }

    .question { page-break-inside: avoid; margin-top: 10pt; }
    .question-title { font-weight: bold; color: #1b3030; margin-bottom: 3pt; }
    .question .grid td { padding: 4pt 8pt 4pt 0; font-size: 8.2pt; }

    .prio { font-family: 'JetBrains Mono', monospace; font-weight: normal; font-size: 9pt; color: #1b3030; }
    .note { margin-top: 8pt; font-size: 8.2pt; color: #5b6b6b; }
    .break { page-break-before: always; }
    .avoid { page-break-inside: avoid; }
</style>
</head>
<body>

<div class="footer">
    <table cellpadding="0" cellspacing="0">
        <tr>
            <td>{{ $t('footer_title', ['domain' => $a->domain]) }}@if ($a->is_demo) · {{ $t('demo_short') }}@endif</td>
            <td style="text-align: right;">{{ $date }} · {{ $t('page') }} <span class="page-number"></span></td>
        </tr>
    </table>
</div>

{{-- Cover --}}
<p class="eyebrow">{{ $t('eyebrow') }}</p>
@if ($a->is_demo)
    <p style="margin-top: 6pt;"><span class="demo-tag">{{ $t('demo') }}</span></p>
@endif
<h1 class="cover-domain">{{ $a->domain }}</h1>
<p class="cover-market">{{ $a->location ? $t('market_location_line', ['market' => $market, 'location' => $a->location]) : $t('market_line', ['market' => $market]) }}</p>

<div class="rule"></div>
<table class="meta" cellpadding="0" cellspacing="0">
    <tr><td class="k">{{ $t('meta.company') }}</td><td>{{ $a->company_name }}</td></tr>
    @if ($a->industry)
        <tr><td class="k">{{ $t('meta.category') }}</td><td>{{ $a->industry }}</td></tr>
    @endif
    <tr><td class="k">{{ $t('meta.date') }}</td><td>{{ $date }}</td></tr>
    <tr><td class="k">{{ $t('meta.language') }}</td><td>{{ $languageName }}</td></tr>
    <tr><td class="k">{{ $t('meta.providers') }}</td><td>{{ collect($report->providers)->map(fn ($p) => $p['name'].($p['model'] ? ' ('.$p['model'].')' : ''))->implode(' · ') }}</td></tr>
    <tr class="meta-last"><td class="k">{{ $t('meta.scope') }}</td><td>{{ $t('meta.scope_text') }}</td></tr>
</table>

<div class="verdict">
    <p class="label">{{ $t('verdict') }}</p>
    <p class="verdict-text">{{ $narrative->verdict }}</p>
</div>

{{-- 01 Executive summary --}}
<div class="section">
    @include('pdf.partials.section-head', ['num' => $num(), 'title' => $t('section.summary')])
    <table class="kpis" cellpadding="0" cellspacing="0">
        <tr>
            <td><div class="kpi-value">{{ $pct($a->visibility_score) }}</div><div class="kpi-label">{{ $t('summary.score') }}</div></td>
            <td><div class="kpi-value">{{ (int) $a->recommendation_count }}/{{ (int) $a->successful_tests }}</div><div class="kpi-label">{{ $t('summary.recommended') }}</div></td>
            <td><div class="kpi-value">{{ (int) $a->failed_tests }}</div><div class="kpi-label">{{ $t('summary.failed') }}</div></td>
            <td><div class="kpi-value">{{ count($actionChecks) }}</div><div class="kpi-label">{{ $t('summary.checks') }} · {{ $t('summary.checks_value', ['count' => count($actionChecks), 'total' => count($report->checks)]) }}</div></td>
        </tr>
    </table>
    @if ($narrative->summary)
        <h3>{{ $t('summary.confirmed') }}</h3>
        <ul class="list">
            @foreach ($narrative->summary as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    @endif
</div>

{{-- 02 Methodology & limits --}}
<div class="section avoid">
    @include('pdf.partials.section-head', ['num' => $num(), 'title' => $t('section.methodology')])
    <h3>{{ $t('method.measured_title') }}</h3>
    <p>{{ $t('method.measured', ['questions' => $questionCount, 'providers' => $providerCount, 'tests' => $questionCount * $providerCount, 'date' => $date, 'language' => $languageName, 'market' => $market]) }}@if ($a->location) {{ $t('method.measured_location', ['location' => $a->location]) }}@endif {{ $t('method.questions_rule') }}</p>
    <p>{{ $t('method.technical', ['domain' => $a->domain]) }}</p>
    <h3>{{ $t('method.score_title') }}</h3>
    <p>{{ $t('method.score') }}</p>
    <h3>{{ $t('method.not_measured_title') }}</h3>
    <p>{{ $t('method.not_measured') }}</p>
    <h3>{{ $t('method.limits_title') }}</h3>
    <p>{{ $t('method.limits') }}</p>
</div>

{{-- 03 AI visibility --}}
<div class="section avoid">
    @include('pdf.partials.section-head', ['num' => $num(), 'title' => $t('section.visibility')])
    <table class="grid" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th>{{ $t('visibility.provider') }}</th>
                <th>{{ $t('visibility.model') }}</th>
                <th class="num">{{ $t('visibility.recommended') }}</th>
                <th class="num">{{ $t('visibility.successful') }}</th>
                <th class="num">{{ $t('visibility.failed') }}</th>
                <th class="num" style="width: 18%;">{{ $t('visibility.share') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report->providers as $p)
                <tr>
                    <td><strong>{{ $p['name'] }}</strong></td>
                    <td class="evidence">{{ $p['model'] ?: $t('none') }}</td>
                    <td class="num">{{ $p['recommended'] }}</td>
                    <td class="num">{{ $p['successful'] }}</td>
                    <td class="num">{{ $p['failed'] }}</td>
                    <td class="num">
                        @if ($p['successful'] > 0)
                            <strong>{{ $pct($p['percentage']) }}</strong>
                            <div class="bar" style="margin-top: 3pt;"><div class="bar-fill" style="width: {{ max(0, min(100, (float) $p['percentage'])) }}%;"></div></div>
                        @else
                            <span class="muted">{{ $t('visibility.no_data') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($report->categories)
        <h3 style="margin-top: 14pt;">{{ $t('visibility.by_category') }}</h3>
        <table class="grid" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>{{ $t('visibility.category') }}</th>
                    <th class="num">{{ $t('visibility.recommended') }}</th>
                    <th class="num">{{ $t('visibility.successful') }}</th>
                    <th class="num" style="width: 18%;">{{ $t('visibility.share') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report->categories as $category => $row)
                    <tr>
                        <td>{{ __('audit.category.'.$category, [], $lang) }}</td>
                        <td class="num">{{ $row['recommended'] }}</td>
                        <td class="num">{{ $row['successful'] }}</td>
                        <td class="num">
                            <strong>{{ $pct($row['percentage']) }}</strong>
                            <div class="bar" style="margin-top: 3pt;"><div class="bar-fill" style="width: {{ max(0, min(100, (float) $row['percentage'])) }}%;"></div></div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- 04 Questions & results --}}
<div class="section break">
    @include('pdf.partials.section-head', ['num' => $num(), 'title' => $t('section.questions')])
    <p class="muted">{{ $t('questions.intro') }}</p>
    @foreach ($report->questions as $q)
        <div class="question">
            <p class="question-title"><span class="mono muted" style="font-weight: normal;">Q{{ str_pad((string) $q['position'], 2, '0', STR_PAD_LEFT) }}</span>@if ($q['category']) <span class="small muted" style="font-weight: normal;">· {{ __('audit.category.'.$q['category'], [], $lang) }}</span>@endif<br>{{ $q['question'] }}</p>
            <table class="grid" cellpadding="0" cellspacing="0">
                @foreach ($q['results'] as $slug => $r)
                    <tr>
                        <td style="width: 16%;">{{ $providerNames[$slug] ?? $slug }}</td>
                        @if ($r['status'] === 'succeeded')
                            <td style="width: 22%;"><span class="tag t-{{ $classTone[$r['classification'] ?? 'not_mentioned'] ?? 'muted' }}">{{ $r['classification'] ? __('analysis.classification.'.$r['classification'], [], $lang) : $t('questions.pending') }}</span></td>
                            <td class="muted">{{ $r['snippet'] ? AuditText::excerpt($r['snippet']) : $t('none') }}</td>
                        @elseif ($r['status'] === 'failed')
                            <td colspan="2"><span class="tag t-fail">{{ $t('questions.failed') }}</span></td>
                        @elseif ($r['status'] === 'skipped')
                            <td colspan="2" class="muted">{{ $t('questions.skipped') }}</td>
                        @else
                            <td colspan="2" class="muted">{{ $t('questions.pending') }}</td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</div>

{{-- 05 Competitors --}}
<div class="section">
    @include('pdf.partials.section-head', ['num' => $num(), 'title' => $t('section.competitors')])
    @if ($report->competitors)
        <p class="muted">{{ $t('competitors.intro') }}</p>
        <table class="grid" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>{{ $t('competitors.name') }}</th>
                    <th>{{ $t('competitors.domain') }}</th>
                    <th class="num">{{ $t('competitors.mentions') }}</th>
                    <th>{{ $t('competitors.providers') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach (array_slice($report->competitors, 0, 15) as $c)
                    <tr>
                        <td><strong>{{ $c['name'] }}</strong></td>
                        <td class="evidence">{{ $c['domain'] ?: $t('none') }}</td>
                        <td class="num">{{ $c['mentions'] }}</td>
                        <td class="muted">{{ collect($c['providers'])->map(fn ($s) => $providerNames[$s] ?? $s)->implode(', ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">{{ $t('competitors.empty') }}</p>
    @endif
</div>

{{-- 06 Technical findings --}}
<div class="section break">
    @include('pdf.partials.section-head', ['num' => $num(), 'title' => $t('section.findings')])
    @if ($report->checks)
        <p class="muted">{{ $t('findings.intro', ['domain' => $a->domain, 'date' => $date]) }}</p>
        <table class="grid" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 24%;">{{ $t('findings.finding') }}</th>
                    <th style="width: 37%;">{{ $t('findings.evidence') }}</th>
                    <th>{{ $t('findings.impact') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report->checks as $check)
                    <tr>
                        <td>
                            <strong>{{ AuditText::check($check, 'title', $a, $lang) }}</strong><br>
                            <span class="dot bg-{{ $statusClass[$check->status->value] }}"></span><span class="small t-{{ $statusClass[$check->status->value] }}">{{ __('audit.status.'.$check->status->value, [], $lang) }}</span>
                        </td>
                        <td class="evidence">{{ $check->evidence }}</td>
                        <td>{{ AuditText::check($check, 'impact', $a, $lang) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">{{ $t('findings.empty') }}</p>
    @endif
</div>

{{-- 07 Priorities --}}
<div class="section break">
    @include('pdf.partials.section-head', ['num' => $num(), 'title' => $t('section.priorities')])
    @if ($report->recommendations)
        <table class="grid" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 12%;">{{ $t('priorities.priority') }}</th>
                    <th style="width: 36%;">{{ $t('priorities.action') }}</th>
                    <th style="width: 29%;">{{ $t('priorities.acceptance') }}</th>
                    <th>{{ $t('priorities.why') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report->recommendations as $rec)
                    <tr>
                        <td><span class="prio">{{ $rec->priority->label() }}</span><br><span class="small muted">{{ $t('priorities.days', ['window' => str_replace('-', '–', $rec->window)]) }}</span></td>
                        <td>{{ AuditText::rec($rec, 'action', $a, $lang) }}</td>
                        <td>{{ AuditText::rec($rec, 'acceptance', $a, $lang) }}</td>
                        <td>{{ AuditText::rec($rec, 'why', $a, $lang) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">{{ $t('priorities.empty') }}</p>
    @endif

    {{-- H1 / title / meta model --}}
    @if ($hasModel)
        <div class="avoid" style="margin-top: 20pt;">
            <h3 style="font-family: 'Afacad SemiBold'; font-weight: normal; font-size: 13pt;">{{ $t('section.model') }}</h3>
            <p class="muted">{{ $t('model.intro') }}</p>
            <table class="grid" cellpadding="0" cellspacing="0">
                @foreach (['h1' => $narrative->h1, 'title' => $narrative->title, 'meta' => $narrative->metaDescription] as $field => $value)
                    @continue(! $value)
                    <tr>
                        <td style="width: 18%;" class="label">{{ $t('model.'.$field) }}</td>
                        <td class="evidence" style="font-size: 8.4pt;">{{ $value }}</td>
                        <td class="num small muted" style="width: 16%;">{{ $t('model.chars', ['count' => mb_strlen($value)]) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</div>

{{-- 08 Plan 30/60/90 --}}
<div class="section avoid">
    @include('pdf.partials.section-head', ['num' => $num(), 'title' => $t('section.plan')])
    <table class="grid" cellpadding="0" cellspacing="0">
        @foreach ($report->plan() as $phase => $recs)
            <tr>
                <td style="width: 18%;"><strong>{{ $t('plan.phase_'.$phase) }}</strong></td>
                <td>
                    @forelse ($recs as $rec)
                        <div style="margin-bottom: 3pt;"><span class="prio" style="font-size: 7.5pt;">{{ $rec->priority->label() }}</span> &nbsp;{{ AuditText::excerpt(AuditText::rec($rec, 'action', $a, $lang), 180) }}</div>
                    @empty
                        <span class="muted">{{ $t('plan.empty') }}</span>
                    @endforelse
                </td>
            </tr>
        @endforeach
    </table>
</div>

{{-- 09 Before & after --}}
<div class="section avoid">
    @include('pdf.partials.section-head', ['num' => $num(), 'title' => $t('section.history')])
    @if ($h = $report->history)
        <p class="muted">{{ $t('history.intro', ['date' => $h->previousDate->copy()->locale($lang)->isoFormat('LL')]) }}</p>
        <table class="grid" cellpadding="0" cellspacing="0">
            <thead>
                <tr><th>{{ $t('history.metric') }}</th><th class="num">{{ $t('history.before') }}</th><th class="num">{{ $t('history.after') }}</th></tr>
            </thead>
            <tbody>
                <tr><td><strong>{{ $t('history.score') }}</strong></td><td class="num">{{ $h->scoreBefore === null ? $t('na') : $pct($h->scoreBefore) }}</td><td class="num"><strong>{{ $h->scoreAfter === null ? $t('na') : $pct($h->scoreAfter) }}</strong></td></tr>
                @foreach ($h->providers as $slug => $row)
                    <tr><td>{{ $providerNames[$slug] ?? $slug }}</td><td class="num">{{ $row['before'] === null ? $t('na') : $pct($row['before']) }}</td><td class="num">{{ $row['after'] === null ? $t('na') : $pct($row['after']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <table class="grid" cellpadding="0" cellspacing="0" style="margin-top: 8pt;">
            @foreach (['fixed' => $h->checksFixed, 'regressed' => $h->checksRegressed] as $label => $keys)
                <tr><td style="width: 30%;">{{ $t('history.'.$label) }}</td><td>{{ $keys ? collect($keys)->map(fn ($k) => __("audit.check.{$k}.title", ['bot' => $k], $lang))->implode(', ') : $t('none') }}</td></tr>
            @endforeach
            <tr><td>{{ $t('history.gained') }}</td><td>{{ $h->competitorsGained ? implode(', ', $h->competitorsGained) : $t('none') }}</td></tr>
            <tr><td>{{ $t('history.lost') }}</td><td>{{ $h->competitorsLost ? implode(', ', $h->competitorsLost) : $t('none') }}</td></tr>
        </table>
    @else
        <p class="muted">{{ $t('history.none') }}</p>
    @endif
</div>

{{-- 10 KPIs --}}
<div class="section avoid">
    @include('pdf.partials.section-head', ['num' => $num(), 'title' => $t('section.kpis')])
    <table class="grid" cellpadding="0" cellspacing="0">
        <thead>
            <tr><th style="width: 32%;">{{ $t('kpis.kpi') }}</th><th style="width: 20%;">{{ $t('kpis.baseline') }}</th><th>{{ $t('kpis.measure') }}</th></tr>
        </thead>
        <tbody>
            <tr><td>{{ $t('summary.score') }}</td><td><strong>{{ $pct($a->visibility_score) }}</strong></td><td>{{ $t('kpis.score_measure', ['questions' => $questionCount]) }}</td></tr>
            <tr><td>{{ $t('kpis.providers') }}</td><td><strong>{{ $t('kpis.providers_value', ['count' => $recommendingProviders, 'total' => $providerCount]) }}</strong></td><td>{{ $t('kpis.providers_measure') }}</td></tr>
            <tr><td>{{ $t('kpis.checks') }}</td><td><strong>{{ $t('kpis.providers_value', ['count' => $passedChecks, 'total' => count($report->checks)]) }}</strong></td><td>{{ $t('kpis.checks_measure') }}</td></tr>
            <tr><td>{{ $t('kpis.citations', ['domain' => $a->domain]) }}</td><td><strong>{{ (int) $a->citation_count }}</strong></td><td>{{ $t('kpis.citations_measure') }}</td></tr>
        </tbody>
    </table>
</div>

{{-- Appendix: sources --}}
<div class="section">
    <table class="section-head" cellpadding="0" cellspacing="0"><tr><td><h2>{{ $t('section.sources') }}</h2></td></tr></table>
    @if ($report->sources)
        <p class="muted">{{ $t('sources.intro') }}</p>
        <table class="grid" cellpadding="0" cellspacing="0">
            <thead><tr><th>{{ $t('sources.url') }}</th><th class="num" style="width: 14%;">{{ $t('sources.count') }}</th></tr></thead>
            <tbody>
                @foreach (array_slice($report->sources, 0, 60) as $source)
                    <tr>
                        <td><span class="evidence">{{ \Illuminate\Support\Str::limit($source['url'], 95) }}</span>@if ($source['title'])<br><span class="small muted">{{ \Illuminate\Support\Str::limit($source['title'], 110) }}</span>@endif</td>
                        <td class="num">{{ $source['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">{{ $t('sources.empty') }}</p>
    @endif
    <p class="note">{{ $t('generated_by') }} · {{ $report->generatedAt->copy()->locale($lang)->isoFormat('LL') }}</p>
</div>

</body>
</html>
