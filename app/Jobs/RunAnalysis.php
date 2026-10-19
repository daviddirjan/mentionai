<?php

namespace App\Jobs;

use App\Actions\DispatchProviderQueries;
use App\Contracts\SiteAuditor;
use App\Data\SiteCheck;
use App\Enums\AnalysisStatus;
use App\Enums\ResponseStatus;
use App\Models\AiProvider;
use App\Models\Analysis;
use App\Services\Analysis\QuestionGenerator;
use App\Services\Inspection\WebsiteInspector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/** Steps 1-3 of the pipeline (plus the site audit for the audit tier); the provider batch then hands over to FinalizeAnalysis. */
class RunAnalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $analysisId) {}

    public function handle(WebsiteInspector $inspector, QuestionGenerator $questions, DispatchProviderQueries $dispatch, SiteAuditor $auditor): void
    {
        $analysis = Analysis::query()->find($this->analysisId);
        if ($analysis === null || $analysis->isFinished()) {
            return;
        }

        $analysis->update(['status' => AnalysisStatus::InspectingWebsite, 'started_at' => $analysis->started_at ?? now()]);
        // Inspection failure must never fail the analysis; the inspector reports it in the returned array.
        $analysis->update(['website_inspection' => $inspector->inspect($analysis->website_url)]);

        if ($analysis->isAudit() && $analysis->site_audit === null) {
            $analysis->update(['status' => AnalysisStatus::AuditingSite]);
            try {
                $checks = $auditor->audit($analysis);
            } catch (Throwable $e) {
                // The auditor must not throw; a broken audit still lets the visibility part run.
                report($e);
                $checks = [];
            }
            $analysis->update(['site_audit' => array_map(fn (SiteCheck $check) => $check->toArray(), $checks)]);
        }

        $analysis->update(['status' => AnalysisStatus::GeneratingQuestions]);
        $generated = $questions->generate($analysis);

        $providers = AiProvider::query()->enabled()->orderBy('sort_order')->get();
        if ($providers->isEmpty()) {
            $this->failEarly($analysis, 'No AI providers are enabled.');

            return;
        }

        $analysis->update(['status' => AnalysisStatus::QueryingProviders]);

        $responseIds = [];
        foreach ($providers as $provider) {
            $cap = max(1, (int) $provider->max_requests_per_analysis);
            if ($analysis->isAudit()) {
                $cap = max($cap, (int) settings('audit_query_count'));
            }
            foreach ($generated->take($cap) as $question) {
                $response = $analysis->responses()->firstOrCreate(
                    ['analysis_question_id' => $question->id, 'provider' => $provider->slug->value],
                    ['ai_provider_id' => $provider->id, 'model' => $provider->model, 'status' => ResponseStatus::Pending],
                );
                if (! $response->status->isFinished()) {
                    $responseIds[] = $response->id;
                }
            }
        }

        $dispatch($analysis, $responseIds);
    }

    public function failed(Throwable $e): void
    {
        $analysis = Analysis::query()->find($this->analysisId);
        if ($analysis !== null && ! $analysis->isFinished()) {
            $analysis->update([
                'status' => AnalysisStatus::Failed,
                'error_message' => Str::limit('Pipeline error: '.$e->getMessage(), 500),
                'completed_at' => now(),
            ]);
        }
        report($e);
    }

    private function failEarly(Analysis $analysis, string $message): void
    {
        $analysis->update(['status' => AnalysisStatus::Failed, 'error_message' => $message, 'completed_at' => now()]);
    }
}
