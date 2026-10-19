<?php

namespace Tests\Feature\Frontend;

use App\Contracts\AuditStarter;
use App\Data\AnalysisStartResult;
use App\Exceptions\AnalysisRejected;
use App\Models\Analysis;
use App\Models\Lead;

class FakeAuditStarter implements AuditStarter
{
    /** @var array{check: Analysis, lead: Lead, ip: string}|null */
    public ?array $received = null;

    public ?AnalysisRejected $reject = null;

    public ?Analysis $audit = null;

    public function start(Analysis $check, Lead $lead, string $ip): AnalysisStartResult
    {
        $this->received = ['check' => $check, 'lead' => $lead, 'ip' => $ip];

        if ($this->reject) {
            throw $this->reject;
        }

        $this->audit ??= Analysis::factory()->audit()->create(['parent_analysis_id' => $check->id]);
        $lead->update(['analysis_id' => $this->audit->id]);

        return new AnalysisStartResult($this->audit, false);
    }
}
