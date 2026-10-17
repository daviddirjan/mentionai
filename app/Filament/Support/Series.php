<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class Series
{
    /**
     * Daily totals for the last $days days (zero-filled). Sums $sumColumn, or counts rows when null.
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public static function daily(Builder $query, string $dateColumn, ?string $sumColumn = null, int $days = 30): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $aggregate = $sumColumn ? "SUM({$sumColumn})" : 'COUNT(*)';

        $rows = (clone $query)
            ->where($dateColumn, '>=', $start)
            ->selectRaw("DATE({$dateColumn}) as day, {$aggregate} as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $values = [];
        for ($i = 0; $i < $days; $i++) {
            $day = Carbon::parse($start)->addDays($i);
            $labels[] = $day->format('M j');
            $values[] = round((float) ($rows[$day->toDateString()] ?? 0), 6);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
