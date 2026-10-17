<?php

namespace App\Filament\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class Csv
{
    /**
     * Streams a query as CSV. $map turns one model into a list of cell values matching $headers.
     *
     * @param  list<string>  $headers
     * @param  Closure(mixed): list<mixed>  $map
     */
    public static function download(string $filename, array $headers, Builder $query, Closure $map): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $query, $map): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($query->lazyById() as $record) {
                fputcsv($out, array_map(self::sanitize(...), $map($record)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralizes spreadsheet formula injection from user-supplied text. */
    private static function sanitize(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
