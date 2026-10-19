<?php

namespace App\Services\Audit;

use App\Contracts\AuditReportBuilder;
use App\Contracts\RecommendationBuilder;
use App\Data\AuditReport;
use App\Enums\ResponseStatus;
use App\Models\AiResponse;
use App\Models\Analysis;
use App\Models\AnalysisQuestion;

/** Assembles everything the audit page and PDF show, from stored rows only. */
class DefaultAuditReportBuilder implements AuditReportBuilder
{
    public const SNIPPET_LENGTH = 220;

    public function __construct(
        private readonly AiAuditNarrator $narrator,
        private readonly RecommendationBuilder $recommendations,
        private readonly HistoryComparer $history,
    ) {}

    public function build(Analysis $analysis): AuditReport
    {
        $facts = AuditFacts::for($analysis);

        return new AuditReport(
            analysis: $analysis,
            // A missing narrative (free check, or a narrator crash) gets the deterministic one; never an AI call here.
            narrative: $analysis->auditNarrative() ?? $this->narrator->rules($analysis, null),
            checks: $analysis->siteChecks(),
            recommendations: $analysis->recommendations !== null ? $analysis->recommendationList() : $this->recommendations->build($analysis),
            providers: $facts->providers(),
            categories: $facts->categories(),
            questions: $this->questions($facts),
            competitors: $facts->competitors->map(fn ($c) => [
                'name' => $c->name,
                'domain' => $c->domain,
                'mentions' => (int) $c->mention_count,
                'providers' => array_values($c->providers ?? []),
            ])->values()->all(),
            sources: $facts->sources(),
            history: $this->history->compare($analysis),
            generatedAt: now(),
        );
    }

    /** @return list<array{position: int, category: ?string, question: string, results: array<string, array{status: string, classification: ?string, snippet: ?string}>}> */
    private function questions(AuditFacts $facts): array
    {
        $byQuestion = $facts->responses->groupBy('analysis_question_id');
        $order = array_flip(array_column($facts->providers(), 'slug'));

        return $facts->questions->map(function (AnalysisQuestion $question) use ($facts, $byQuestion, $order) {
            $results = [];
            foreach ($byQuestion->get($question->id, collect())->sortBy(fn (AiResponse $r) => $order[$r->provider] ?? 99) as $response) {
                $succeeded = $response->status === ResponseStatus::Succeeded;
                $results[$response->provider] = [
                    'status' => $response->status->value,
                    'classification' => $succeeded ? $response->brandMention?->classification?->value : null,
                    'snippet' => $succeeded ? $this->snippet($response) : null,
                ];
            }

            return [
                'position' => (int) $question->position,
                'category' => $facts->categoryOf($question),
                'question' => $question->question,
                'results' => $results,
            ];
        })->values()->all();
    }

    /** The detection evidence around the brand when there is one, else the start of the answer. */
    private function snippet(AiResponse $response): ?string
    {
        $text = null;
        foreach ($response->brandMention?->evidence ?? [] as $evidence) {
            if (is_string($evidence['snippet'] ?? null) && trim($evidence['snippet']) !== '') {
                $text = $evidence['snippet'];
                break;
            }
        }
        $text ??= (string) $response->response_text;

        $text = trim(preg_replace('/\s+/u', ' ', str_replace(['**', '__', '#', '`'], '', strip_tags($text))));
        if ($text === '') {
            return null;
        }

        return mb_strlen($text) <= self::SNIPPET_LENGTH ? $text : rtrim(mb_substr($text, 0, self::SNIPPET_LENGTH - 1)).'…';
    }
}
