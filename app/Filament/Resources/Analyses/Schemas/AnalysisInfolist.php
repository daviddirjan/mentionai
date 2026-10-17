<?php

namespace App\Filament\Resources\Analyses\Schemas;

use App\Enums\MentionClassification;
use App\Enums\ResponseStatus;
use App\Models\AiResponse;
use App\Models\Analysis;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AnalysisInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Input')->columns(4)->schema([
                TextEntry::make('company_name')->label('Company'),
                TextEntry::make('website_url')->label('Website')->url(fn (Analysis $record) => $record->website_url, true),
                TextEntry::make('market'),
                TextEntry::make('language'),
                TextEntry::make('industry')->placeholder('n/a'),
                TextEntry::make('interface_locale')->label('Interface locale'),
                TextEntry::make('status')->badge(),
                TextEntry::make('is_demo')->label('Data')->badge()->color('warning')
                    ->state(fn (Analysis $record) => $record->is_demo ? 'DEMO' : 'Live'),
                TextEntry::make('visibility_score')->label('Score')->suffix('%')->placeholder('n/a'),
                TextEntry::make('successful_tests')->label('Successful tests'),
                TextEntry::make('failed_tests')->label('Failed tests'),
                TextEntry::make('estimated_cost')->label('Estimated cost')->prefix('$')->helperText('Estimate, not an invoice'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('completed_at')->dateTime()->placeholder('n/a'),
                TextEntry::make('error_message')->placeholder('none')->columnSpan(2),
            ]),

            Section::make('Website inspection')->collapsible()->collapsed()->schema([
                ViewEntry::make('website_inspection')->label('')->view('filament.analysis.json')
                    ->state(fn (Analysis $record) => $record->website_inspection),
            ]),

            Section::make('Scores per provider')->schema([
                RepeatableEntry::make('provider_scores_list')->label('')->columns(5)
                    ->state(fn (Analysis $record) => collect($record->provider_scores ?? [])
                        ->map(fn (array $s, string $slug) => [
                            'provider' => $slug,
                            'recommended' => $s['recommended'] ?? 0,
                            'successful' => $s['successful'] ?? 0,
                            'total' => $s['total'] ?? 0,
                            'percentage' => ($s['percentage'] ?? 0).'%',
                        ])->values()->all())
                    ->schema([
                        TextEntry::make('provider')->badge(),
                        TextEntry::make('recommended'),
                        TextEntry::make('successful'),
                        TextEntry::make('total'),
                        TextEntry::make('percentage')->label('Score'),
                    ]),
            ]),

            Section::make('Questions and responses')->schema([
                RepeatableEntry::make('questions')->label('')->schema([
                    TextEntry::make('question')->label(fn ($record) => 'Question #'.$record->position)->weight('bold'),
                    TextEntry::make('template_snapshot')->label('Template used (snapshot)')->placeholder('n/a'),
                    RepeatableEntry::make('responses')->label('Responses')->schema([
                        TextEntry::make('provider')->badge(),
                        TextEntry::make('model'),
                        TextEntry::make('status')->badge()->color(fn (ResponseStatus $state) => match ($state) {
                            ResponseStatus::Succeeded => 'success',
                            ResponseStatus::Failed => 'danger',
                            default => 'gray',
                        }),
                        TextEntry::make('classification')->badge()
                            ->state(fn (AiResponse $record) => $record->brandMention?->classification)
                            ->color(fn (?MentionClassification $state) => match ($state) {
                                MentionClassification::Recommended => 'success',
                                MentionClassification::Mentioned, MentionClassification::CitedOnly => 'info',
                                MentionClassification::Uncertain => 'warning',
                                default => 'gray',
                            })->placeholder('n/a'),
                        TextEntry::make('response_text')->label('Response')->columnSpanFull()->placeholder('no response')->extraAttributes(['style' => 'white-space: pre-wrap']),
                        TextEntry::make('citations')->columnSpanFull()->listWithLineBreaks()->bulleted()
                            ->state(fn (AiResponse $record) => collect($record->citations ?? [])->map(fn ($c) => trim(($c['title'] ?? '').' '.($c['url'] ?? '')))->all())
                            ->placeholder('none'),
                        TextEntry::make('evidence')->columnSpanFull()->listWithLineBreaks()->bulleted()
                            ->state(fn (AiResponse $record) => collect($record->brandMention?->evidence ?? [])->map(fn ($e) => ($e['type'] ?? '').': '.($e['term'] ?? '').' - '.($e['snippet'] ?? ''))->all())
                            ->placeholder('none'),
                        TextEntry::make('input_tokens')->label('Input tokens')->placeholder('n/a'),
                        TextEntry::make('output_tokens')->label('Output tokens')->placeholder('n/a'),
                        TextEntry::make('estimated_cost')->label('Cost (est.)')->prefix('$'),
                        TextEntry::make('duration_ms')->label('Duration')->suffix(' ms')->placeholder('n/a'),
                        TextEntry::make('error')->label('Error')->columnSpanFull()
                            ->state(fn (AiResponse $record) => $record->error_code ? "{$record->error_code}: {$record->error_message}" : null)
                            ->color('danger')->placeholder('none'),
                        ViewEntry::make('raw_response')->label('Raw response (admin only)')->view('filament.analysis.json')->columnSpanFull()
                            ->state(fn (AiResponse $record) => $record->raw_response),
                    ])->columns(4)->contained(false),
                ])->columns(1),
            ]),

            Section::make('Competitors')->collapsible()->schema([
                RepeatableEntry::make('competitors')->label('')->columns(4)->schema([
                    TextEntry::make('name'),
                    TextEntry::make('domain')->placeholder('n/a'),
                    TextEntry::make('mention_count')->label('Mentions'),
                    TextEntry::make('providers')->badge(),
                ]),
            ]),

            Section::make('API usage logs')->collapsible()->collapsed()->schema([
                RepeatableEntry::make('usageLogs')->label('')->columns(6)->schema([
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('provider')->badge(),
                    TextEntry::make('operation'),
                    TextEntry::make('status'),
                    TextEntry::make('estimated_cost')->label('Cost (est.)')->prefix('$'),
                    TextEntry::make('duration_ms')->suffix(' ms')->placeholder('n/a'),
                ]),
            ]),
        ]);
    }
}
