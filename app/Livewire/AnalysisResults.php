<?php

namespace App\Livewire;

use App\Enums\AnalysisStatus;
use App\Enums\ProviderSlug;
use App\Enums\ResponseStatus;
use App\Livewire\Concerns\UsesLocale;
use App\Models\Analysis;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AnalysisResults extends Component
{
    use UsesLocale;

    public const LOW_SCORE_THRESHOLD = 50;

    /** Status steps shown while running, in pipeline order. */
    public const PROGRESS_STEPS = [
        AnalysisStatus::Pending,
        AnalysisStatus::InspectingWebsite,
        AnalysisStatus::GeneratingQuestions,
        AnalysisStatus::QueryingProviders,
        AnalysisStatus::Analyzing,
        AnalysisStatus::Scoring,
    ];

    #[Locked]
    public string $uuid = '';

    #[Locked]
    public bool $reused = false;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $this->reused = session('analysis_reused') === $uuid;
    }

    #[Computed]
    public function analysis(): Analysis
    {
        return Analysis::where('uuid', $this->uuid)
            ->with(['competitors', 'questions.responses.brandMention'])
            ->firstOrFail();
    }

    /** @return array{done: int, total: int} real counts of provider answers received so far */
    #[Computed]
    public function responseProgress(): array
    {
        $statuses = $this->analysis->responses()->pluck('status');

        return [
            'done' => $statuses->filter(fn ($s) => $s instanceof ResponseStatus && $s->isFinished())->count(),
            'total' => $statuses->count(),
        ];
    }

    /** @return list<array{slug: ProviderSlug, name: string, state: string, recommended: int, successful: int, total: int, percentage: float}> */
    #[Computed]
    public function providers(): array
    {
        $scores = $this->analysis->provider_scores ?? [];

        return collect(ProviderSlug::cases())->map(function (ProviderSlug $slug) use ($scores) {
            $score = $scores[$slug->value] ?? null;
            $total = (int) ($score['total'] ?? 0);
            $successful = (int) ($score['successful'] ?? 0);
            $recommended = (int) ($score['recommended'] ?? 0);

            $state = match (true) {
                $total === 0 => 'not_run',
                $successful === 0 => 'failed',
                $recommended > 0 => 'recommended',
                $successful < $total => 'partial',
                default => 'not_recommended',
            };

            return [
                'slug' => $slug,
                'name' => $slug->displayName(),
                'state' => $state,
                'recommended' => $recommended,
                'successful' => $successful,
                'total' => $total,
                'percentage' => (float) ($score['percentage'] ?? 0),
            ];
        })->all();
    }

    public function stepIndex(): int
    {
        $index = array_search($this->analysis->status, self::PROGRESS_STEPS, true);

        return $index === false ? 0 : $index;
    }

    public function render(): View
    {
        return view('livewire.analysis-results');
    }
}
