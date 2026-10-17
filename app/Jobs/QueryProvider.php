<?php

namespace App\Jobs;

use App\Contracts\ProviderRegistry;
use App\Data\ProviderQuery;
use App\Data\ProviderResult;
use App\Enums\ResponseStatus;
use App\Models\AiResponse;
use App\Models\Analysis;
use App\Models\ApiUsageLog;
use App\Services\Ai\Adapters\MockAdapter;
use App\Services\Analysis\SpendingGuard;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/** Executes exactly one ai_responses row. Idempotent: a succeeded row is never called again. */
class QueryProvider implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $responseId) {}

    public function handle(ProviderRegistry $registry, SpendingGuard $guard): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $response = AiResponse::query()->find($this->responseId);
        if ($response === null || $response->status === ResponseStatus::Succeeded) {
            return;
        }

        $analysis = Analysis::query()->find($response->analysis_id);
        $provider = $response->aiProvider()->first();
        $question = $response->question()->first();
        if ($analysis === null || $provider === null || $question === null) {
            $response->update(['status' => ResponseStatus::Failed, 'error_code' => 'missing_context', 'error_message' => 'Provider or question no longer exists.', 'completed_at' => now()]);

            return;
        }

        if (! $provider->is_enabled) {
            $this->skip($response, 'provider_disabled', 'This provider is disabled.');

            return;
        }

        if (($reason = $guard->blockReason($analysis)) !== null) {
            $this->skip($response, $reason, $reason === 'budget_exceeded' ? 'A spending limit was reached before this call.' : 'Analyses are currently disabled.');

            return;
        }

        $response->update([
            'status' => ResponseStatus::Running,
            'attempts' => $response->attempts + 1,
            'started_at' => now(),
            'completed_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);

        try {
            $adapter = $registry->adapterFor($provider);
            if ($adapter instanceof MockAdapter) {
                $adapter = $adapter->forBrand($analysis->company_name, $analysis->domain);
            }
            $result = $adapter->query($provider, new ProviderQuery($question->question, $analysis->language, $analysis->market));
        } catch (Throwable $e) {
            // Adapters must not throw; guard anyway so one provider can never break the batch.
            $result = ProviderResult::failure($provider->slug->value, (string) $provider->model, 'provider_error', 'Unexpected adapter error.');
            report($e);
        }

        $cost = $provider->estimateCost($result->inputTokens, $result->outputTokens, $result->searchCalls);
        $isDemo = $result->isDemo || $registry->isMockMode();

        $response->update([
            'status' => $result->successful ? ResponseStatus::Succeeded : ResponseStatus::Failed,
            'model' => $result->model,
            'is_demo' => $isDemo,
            'raw_response' => $result->raw,
            'response_text' => $result->text,
            'citations' => $result->citations,
            'duration_ms' => $result->durationMs,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'search_calls' => $result->searchCalls,
            'estimated_cost' => $isDemo ? 0 : $cost,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage ? Str::limit($result->errorMessage, 500) : null,
            'completed_at' => now(),
        ]);

        ApiUsageLog::create([
            'analysis_id' => $analysis->id,
            'ai_response_id' => $response->id,
            'ai_provider_id' => $provider->id,
            'provider' => $provider->slug->value,
            'model' => $result->model,
            'operation' => 'query',
            'status' => $result->successful ? 'succeeded' : 'failed',
            'is_demo' => $isDemo,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'search_calls' => $result->searchCalls,
            'estimated_cost' => $isDemo ? 0 : $cost,
            'duration_ms' => $result->durationMs,
            'error_code' => $result->errorCode,
            'created_at' => now(),
        ]);

        if (! $isDemo && $cost > 0) {
            Analysis::query()->whereKey($analysis->id)->increment('estimated_cost', $cost);
        }
    }

    private function skip(AiResponse $response, string $code, string $message): void
    {
        $response->update([
            'status' => ResponseStatus::Skipped,
            'error_code' => $code,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }
}
