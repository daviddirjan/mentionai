<?php

namespace App\Data;

use App\Models\Analysis;
use Carbon\CarbonInterface;

/**
 * Everything the GEO audit PDF and the audit results page need, already computed.
 * Views only format this; they never query or calculate.
 */
final readonly class AuditReport
{
    public function __construct(
        public Analysis $analysis,
        public AuditNarrative $narrative,
        /** @var list<SiteCheck> */
        public array $checks,
        /** @var list<Recommendation> sorted P0 → P2 */
        public array $recommendations,
        /** @var list<array{slug: string, name: string, model: string, recommended: int, successful: int, failed: int, percentage: float}> */
        public array $providers,
        /** @var array<string, array{recommended: int, successful: int, percentage: float}> keyed by QuestionCategory value */
        public array $categories,
        /**
         * @var list<array{position: int, category: ?string, question: string,
         *   results: array<string, array{status: string, classification: ?string, snippet: ?string}>}>
         * results keyed by provider slug; status is the ResponseStatus value, classification the MentionClassification value
         */
        public array $questions,
        /** @var list<array{name: string, domain: ?string, mentions: int, providers: list<string>}> */
        public array $competitors,
        /** @var list<array{url: string, title: ?string, count: int}> distinct cited URLs */
        public array $sources,
        public ?HistoryComparison $history,
        public CarbonInterface $generatedAt,
    ) {}

    /** @return array<int, list<Recommendation>> recommendations grouped by 30/60/90 plan phase (1, 2, 3) */
    public function plan(): array
    {
        $plan = [1 => [], 2 => [], 3 => []];
        foreach ($this->recommendations as $rec) {
            $plan[$rec->priority->planPhase()][] = $rec;
        }

        return $plan;
    }
}
