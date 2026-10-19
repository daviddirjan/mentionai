<?php

namespace App\Livewire;

use App\Contracts\AuditReportBuilder;
use App\Data\AuditReport;
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

    /** @return list<AnalysisStatus> steps shown while running (the final "completed" step is the result itself) */
    public function progressSteps(): array
    {
        return array_values(array_filter(
            AnalysisStatus::progressSteps($this->analysis->tier),
            fn (AnalysisStatus $step) => $step !== AnalysisStatus::Completed,
        ));
    }

    public function stepIndex(): int
    {
        // inspecting_website is not a listed step; it belongs to the start of the pipeline.
        $index = array_search($this->analysis->status, $this->progressSteps(), true);

        return $index === false ? 0 : $index;
    }

    /** Assembled from stored data only; null until a completed audit exists. */
    #[Computed]
    public function report(): ?AuditReport
    {
        $analysis = $this->analysis;

        if (! $analysis->isAudit() || $analysis->status !== AnalysisStatus::Completed) {
            return null;
        }

        return app(AuditReportBuilder::class)->build($analysis);
    }

    public function hasReportAccess(): bool
    {
        return session("audit_access.{$this->uuid}") === true;
    }

    public function auditEnabled(): bool
    {
        return (bool) settings('audit_enabled', true);
    }

    public function auditTests(): int
    {
        return (int) settings('audit_query_count', 25) * count(ProviderSlug::cases());
    }

    public function render(): View
    {
        return view('livewire.analysis-results');
    }
}
