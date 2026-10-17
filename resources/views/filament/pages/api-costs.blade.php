@php
    $money = fn ($v) => '$'.number_format((float) $v, 4);
    $th = 'text-align: left; padding: 0.4rem 0.75rem; font-weight: 600;';
    $td = 'padding: 0.4rem 0.75rem; border-top: 1px solid rgba(127,127,127,0.25);';
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <strong>Estimated costs, not invoices.</strong>
        Figures are calculated from the token and search counts reported by providers and the prices configured under AI providers.
        Your provider dashboards remain the source of truth for billing.

        <div style="margin-top: 0.75rem;">
            <label style="display: inline-flex; align-items: center; gap: 0.5rem;">
                <x-filament::input.checkbox wire:model.live="includeDemo" />
                <span>Include demo / mock usage</span>
            </label>
        </div>
    </x-filament::section>

    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));">
        @foreach ([
            'Total calls' => number_format($totals['calls']),
            'Failed calls' => number_format($totals['failed']),
            'Input tokens' => number_format($totals['input_tokens']),
            'Output tokens' => number_format($totals['output_tokens']),
            'Estimated total' => $money($totals['cost']),
            'Estimated this month' => $money($totals['month_cost']),
            'Estimated today' => $money($totals['today_cost']),
            'Average per analysis' => $money($average),
        ] as $label => $value)
            <x-filament::section>
                <div style="font-size: 0.8rem; opacity: 0.7;">{{ $label }}</div>
                <div style="font-size: 1.5rem; font-weight: 700;">{{ $value }}</div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section heading="Cost per provider (estimated)">
        <table style="width: 100%; font-size: 0.875rem;">
            <thead><tr><th style="{{ $th }}">Provider</th><th style="{{ $th }}">Calls</th><th style="{{ $th }}">Failed</th><th style="{{ $th }}">Input tokens</th><th style="{{ $th }}">Output tokens</th><th style="{{ $th }}">Cost</th></tr></thead>
            <tbody>
                @forelse ($providers as $row)
                    <tr>
                        <td style="{{ $td }}">{{ $row->provider }}</td>
                        <td style="{{ $td }}">{{ number_format($row->calls) }}</td>
                        <td style="{{ $td }}">{{ number_format($row->failed) }}</td>
                        <td style="{{ $td }}">{{ number_format($row->input_tokens) }}</td>
                        <td style="{{ $td }}">{{ number_format($row->output_tokens) }}</td>
                        <td style="{{ $td }}">{{ $money($row->cost) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="{{ $td }}">No usage recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr));">
        <x-filament::section heading="Monthly totals (estimated)">
            <table style="width: 100%; font-size: 0.875rem;">
                <tbody>
                    @foreach ($monthly as $month => $cost)
                        <tr><td style="{{ $td }}">{{ $month }}</td><td style="{{ $td }}">{{ $money($cost) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section heading="Daily, last 30 days (estimated)">
            <table style="width: 100%; font-size: 0.875rem;">
                <tbody>
                    @forelse ($daily as $day => $cost)
                        <tr><td style="{{ $td }}">{{ $day }}</td><td style="{{ $td }}">{{ $money($cost) }}</td></tr>
                    @empty
                        <tr><td style="{{ $td }}">No usage in the last 30 days.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    </div>

    <x-filament::section heading="Cost per analysis (latest 20, estimated)">
        <table style="width: 100%; font-size: 0.875rem;">
            <thead><tr><th style="{{ $th }}">Company</th><th style="{{ $th }}">Date</th><th style="{{ $th }}">Successful / failed</th><th style="{{ $th }}">Cost</th></tr></thead>
            <tbody>
                @forelse ($analyses as $analysis)
                    <tr>
                        <td style="{{ $td }}">
                            <a href="{{ \App\Filament\Resources\Analyses\AnalysisResource::getUrl('view', ['record' => $analysis]) }}" style="text-decoration: underline;">{{ $analysis->company_name }}</a>
                        </td>
                        <td style="{{ $td }}">{{ $analysis->created_at->format('Y-m-d H:i') }}</td>
                        <td style="{{ $td }}">{{ $analysis->successful_tests }} / {{ $analysis->failed_tests }}</td>
                        <td style="{{ $td }}">{{ $money($analysis->estimated_cost) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="{{ $td }}">No analyses with recorded cost yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    {{ $this->content }}
</x-filament-panels::page>
