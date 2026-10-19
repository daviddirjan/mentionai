<?php

namespace App\Filament\Resources\Analyses\Schemas;

use App\Enums\CheckStatus;
use App\Enums\MentionClassification;
use App\Enums\Priority;
use App\Enums\ResponseStatus;
use App\Filament\Resources\Analyses\AnalysisResource;
use App\Filament\Resources\Analyses\Tables\AnalysesTable;
use App\Models\AiResponse;
use App\Models\Analysis;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class AnalysisInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Input')->columns(4)->schema([
                TextEntry::make('company_name')->label('Company'),
                TextEntry::make('website_url')->label('Website')->url(fn (Analysis $record) => $record->website_url, true),
                TextEntry::make('market'),
                TextEntry::make('location')->label('City / region')->placeholder('n/a'),
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

            ...self::auditSections(),

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

    /** @return list<Section> */
    private static function auditSections(): array
    {
        $isAudit = fn (Analysis $record) => $record->isAudit();

        return [
            Section::make('GEO audit')->columns(4)->visible($isAudit)->schema([
                TextEntry::make('tier')->badge()->color('primary')
                    ->formatStateUsing(fn ($state) => AnalysesTable::tierOptions()[$state->value]),
                TextEntry::make('parent_check')->label('Started from check')
                    ->state(fn (Analysis $record) => $record->parent?->uuid)
                    ->url(fn (Analysis $record) => $record->parent ? AnalysisResource::getUrl('view', ['record' => $record->parent]) : null)
                    ->fontFamily(FontFamily::Mono)->placeholder('n/a'),
                TextEntry::make('report_generated_at')->label('Report generated at')->dateTime()->placeholder('not generated'),
            ]),

            Section::make('Technical checks')->visible($isAudit)->collapsible()->schema([
                RepeatableEntry::make('site_checks_list')->label('')
                    ->state(fn (Analysis $record) => collect($record->siteChecks())->map(fn ($c) => [
                        'group' => $c->group->value,
                        'key' => $c->key,
                        'status' => $c->status,
                        'evidence' => $c->evidence,
                        'params' => self::params($c->params),
                    ])->all())
                    ->table([
                        TableColumn::make('Group'),
                        TableColumn::make('Check'),
                        TableColumn::make('Status'),
                        TableColumn::make('Evidence'),
                        TableColumn::make('Params'),
                    ])
                    ->schema([
                        TextEntry::make('group')->badge()->color('gray'),
                        TextEntry::make('key')->fontFamily(FontFamily::Mono),
                        TextEntry::make('status')->badge()
                            ->formatStateUsing(fn (CheckStatus $state) => $state->value)
                            ->color(fn (CheckStatus $state) => match ($state) {
                                CheckStatus::Pass => 'success',
                                CheckStatus::Warn => 'warning',
                                CheckStatus::Fail => 'danger',
                                CheckStatus::Info => 'gray',
                            }),
                        TextEntry::make('evidence')->fontFamily(FontFamily::Mono),
                        TextEntry::make('params')->fontFamily(FontFamily::Mono)->placeholder('—'),
                    ])
                    ->placeholder('No technical checks recorded'),
            ]),

            Section::make('Recommendations')->visible($isAudit)->collapsible()->schema([
                RepeatableEntry::make('recommendations_list')->label('')
                    ->state(fn (Analysis $record) => collect($record->recommendationList())->map(fn ($r) => [
                        'priority' => $r->priority,
                        'window' => $r->window.' days',
                        'key' => $r->key,
                        'source' => $r->source,
                        'params' => self::params($r->params),
                    ])->all())
                    ->table([
                        TableColumn::make('Priority'),
                        TableColumn::make('Window'),
                        TableColumn::make('Recommendation'),
                        TableColumn::make('Source'),
                        TableColumn::make('Params'),
                    ])
                    ->schema([
                        TextEntry::make('priority')->badge()
                            ->formatStateUsing(fn (Priority $state) => $state->label())
                            ->color(fn (Priority $state) => match ($state) {
                                Priority::P0 => 'danger',
                                Priority::P1 => 'warning',
                                Priority::P2 => 'gray',
                            }),
                        TextEntry::make('window'),
                        TextEntry::make('key')->fontFamily(FontFamily::Mono),
                        TextEntry::make('source')->fontFamily(FontFamily::Mono),
                        TextEntry::make('params')->fontFamily(FontFamily::Mono)->placeholder('—'),
                    ])
                    ->placeholder('No recommendations recorded'),
            ]),

            Section::make('Narrative')->visible($isAudit)->collapsible()->columns(2)->schema([
                TextEntry::make('narrative_source')->label('Source')->badge()
                    ->state(fn (Analysis $record) => $record->auditNarrative()?->source)
                    ->formatStateUsing(fn (string $state) => $state === 'demo' ? 'DEMO' : $state)
                    ->color(fn (string $state) => match ($state) {
                        'demo' => 'warning',
                        'ai' => 'success',
                        default => 'gray',
                    })
                    ->placeholder('not generated'),
                TextEntry::make('narrative_verdict')->label('Verdict')->columnSpanFull()
                    ->state(fn (Analysis $record) => $record->auditNarrative()?->verdict)->placeholder('n/a'),
                TextEntry::make('narrative_summary')->label('Summary')->columnSpanFull()->listWithLineBreaks()->bulleted()
                    ->state(fn (Analysis $record) => $record->auditNarrative()?->summary ?? [])->placeholder('n/a'),
                TextEntry::make('narrative_h1')->label('Proposed H1')
                    ->state(fn (Analysis $record) => $record->auditNarrative()?->h1)->placeholder('n/a'),
                TextEntry::make('narrative_title')->label('Proposed title')
                    ->state(fn (Analysis $record) => $record->auditNarrative()?->title)->placeholder('n/a'),
                TextEntry::make('narrative_meta')->label('Proposed meta description')->columnSpanFull()
                    ->state(fn (Analysis $record) => $record->auditNarrative()?->metaDescription)->placeholder('n/a'),
            ]),
        ];
    }

    /** @param  array<string, scalar|null>  $params */
    private static function params(array $params): ?string
    {
        if ($params === []) {
            return null;
        }

        return collect($params)->map(fn ($v, $k) => $k.'='.(is_bool($v) ? var_export($v, true) : (string) $v))->implode(', ');
    }
}
