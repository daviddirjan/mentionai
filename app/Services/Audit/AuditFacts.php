<?php

namespace App\Services\Audit;

use App\Enums\MentionClassification;
use App\Enums\QuestionCategory;
use App\Enums\ResponseStatus;
use App\Models\AiProvider;
use App\Models\AiResponse;
use App\Models\Analysis;
use App\Models\AnalysisQuestion;
use App\Models\Competitor;
use App\Services\Detection\TextNormalizer;
use Illuminate\Support\Collection;

/**
 * Aggregated visibility facts of one analysis, computed once from stored rows (no API calls).
 * Shared by the recommendation rules, the narrator and the report builder so all three agree.
 * Only successful responses enter any percentage (failures are never "not mentioned").
 */
final class AuditFacts
{
    /** @var Collection<int, AnalysisQuestion> */
    public readonly Collection $questions;

    /** @var Collection<int, AiResponse> */
    public readonly Collection $responses;

    /** @var Collection<int, AiResponse> */
    public readonly Collection $successful;

    /** @var Collection<int, Competitor> */
    public readonly Collection $competitors;

    /** @var Collection<string, AiProvider> keyed by slug */
    private readonly Collection $providerRows;

    public function __construct(public readonly Analysis $analysis)
    {
        $this->questions = $analysis->questions()->with('promptTemplate')->get();
        $this->responses = $analysis->responses()->with('brandMention')->get();
        $this->successful = $this->responses->filter(fn (AiResponse $r) => $r->status === ResponseStatus::Succeeded)->values();
        $this->competitors = $analysis->competitors()->get();
        $this->providerRows = AiProvider::query()->orderBy('sort_order')->get()->keyBy(fn (AiProvider $p) => $p->slug->value);
    }

    public static function for(Analysis $analysis): self
    {
        return new self($analysis);
    }

    public function isRecommended(AiResponse $response): bool
    {
        return $response->status === ResponseStatus::Succeeded
            && $response->brandMention?->classification === MentionClassification::Recommended;
    }

    /** Category of a question from its template; questions without one count as "top". */
    public function categoryOf(AnalysisQuestion $question): string
    {
        return $question->promptTemplate?->category?->value ?? QuestionCategory::Top->value;
    }

    public function providerName(string $slug): string
    {
        $row = $this->providerRows->get($slug);

        return $row?->display_name ?: ($row?->slug->displayName() ?? ucfirst($slug));
    }

    public function providerModel(string $slug, ?string $fallback = null): string
    {
        return (string) ($fallback ?? $this->providerRows->get($slug)?->model ?? '');
    }

    /** @return list<array{slug: string, name: string, model: string, recommended: int, successful: int, failed: int, percentage: float}> */
    public function providers(): array
    {
        $order = $this->providerRows->keys()->flip();
        $out = [];
        foreach ($this->responses->groupBy('provider') as $slug => $rows) {
            $ok = $rows->filter(fn (AiResponse $r) => $r->status === ResponseStatus::Succeeded);
            $recommended = $ok->filter(fn (AiResponse $r) => $this->isRecommended($r))->count();
            $out[] = [
                'slug' => (string) $slug,
                'name' => $this->providerName((string) $slug),
                'model' => $this->providerModel((string) $slug, $ok->first()?->model ?? $rows->first()?->model),
                'recommended' => $recommended,
                'successful' => $ok->count(),
                'failed' => $rows->filter(fn (AiResponse $r) => in_array($r->status, [ResponseStatus::Failed, ResponseStatus::Skipped], true))->count(),
                'percentage' => self::percent($recommended, $ok->count()),
            ];
        }
        usort($out, fn ($a, $b) => ($order[$a['slug']] ?? 99) <=> ($order[$b['slug']] ?? 99));

        return $out;
    }

    /** @return array<string, array{recommended: int, successful: int, percentage: float}> keyed by QuestionCategory value, in enum order */
    public function categories(): array
    {
        $categoryByQuestion = $this->questions->mapWithKeys(fn (AnalysisQuestion $q) => [$q->id => $this->categoryOf($q)]);
        $out = [];
        foreach (QuestionCategory::cases() as $case) {
            $rows = $this->successful->filter(fn (AiResponse $r) => ($categoryByQuestion[$r->analysis_question_id] ?? 'top') === $case->value);
            if ($this->questions->contains(fn (AnalysisQuestion $q) => $this->categoryOf($q) === $case->value)) {
                $recommended = $rows->filter(fn (AiResponse $r) => $this->isRecommended($r))->count();
                $out[$case->value] = ['recommended' => $recommended, 'successful' => $rows->count(), 'percentage' => self::percent($recommended, $rows->count())];
            }
        }

        return $out;
    }

    /** Questions of a category that no successful response recommended the brand for. */
    public function unrecommendedQuestions(string $category): Collection
    {
        return $this->questions
            ->filter(fn (AnalysisQuestion $q) => $this->categoryOf($q) === $category)
            ->filter(fn (AnalysisQuestion $q) => ! $this->successful->contains(fn (AiResponse $r) => $r->analysis_question_id === $q->id && $this->isRecommended($r)))
            ->values();
    }

    /**
     * Competitors named in at least $share of the successful tests where the brand was not recommended.
     *
     * @return list<array{name: string, count: int}> most frequent first
     */
    public function competitorGap(float $share = 0.25): array
    {
        $missed = $this->successful->reject(fn (AiResponse $r) => $this->isRecommended($r));
        if ($missed->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($this->competitors as $competitor) {
            $pattern = TextNormalizer::boundaryPattern(TextNormalizer::fold($competitor->name));
            $count = $missed->filter(fn (AiResponse $r) => (bool) preg_match($pattern, TextNormalizer::fold((string) $r->response_text)))->count();
            if ($count / $missed->count() >= $share) {
                $out[] = ['name' => $competitor->name, 'count' => $count];
            }
        }
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $out;
    }

    /** @return list<string> slugs with at least $min successful tests and none recommended */
    public function providerGaps(int $min = 2): array
    {
        return array_values(array_map(fn ($p) => $p['slug'], array_filter($this->providers(), fn ($p) => $p['successful'] >= $min && $p['recommended'] === 0)));
    }

    public function brandCited(): bool
    {
        foreach ($this->successful as $response) {
            foreach ($response->citations ?? [] as $citation) {
                $host = TextNormalizer::domainHost((string) ($citation['url'] ?? ''));
                if (TextNormalizer::hostMatchesDomain($host, $this->analysis->domain)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<array{url: string, title: ?string, count: int}> distinct cited URLs, most cited first */
    public function sources(): array
    {
        $out = [];
        foreach ($this->successful as $response) {
            foreach ($response->citations ?? [] as $citation) {
                $url = trim((string) ($citation['url'] ?? ''));
                if ($url === '' || ! preg_match('#^https?://#i', $url)) {
                    continue;
                }
                $out[$url] ??= ['url' => $url, 'title' => null, 'count' => 0];
                $out[$url]['count']++;
                $title = $citation['title'] ?? null;
                if ($out[$url]['title'] === null && is_string($title) && trim($title) !== '') {
                    $out[$url]['title'] = trim($title);
                }
            }
        }
        $out = array_values($out);
        usort($out, fn ($a, $b) => [$b['count'], $a['url']] <=> [$a['count'], $b['url']]);

        return $out;
    }

    public function score(): ?float
    {
        return $this->analysis->visibility_score === null ? null : (float) $this->analysis->visibility_score;
    }

    public static function percent(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : 0.0;
    }
}
