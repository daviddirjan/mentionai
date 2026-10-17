<?php

namespace Tests\Feature\Frontend;

use App\Contracts\AnalysisStarter;
use App\Data\AnalysisInput;
use App\Data\AnalysisStartResult;
use App\Exceptions\AnalysisRejected;
use App\Models\Analysis;

class FakeAnalysisStarter implements AnalysisStarter
{
    public ?AnalysisInput $received = null;

    public ?AnalysisRejected $reject = null;

    public bool $reused = false;

    public ?Analysis $analysis = null;

    public function start(AnalysisInput $input): AnalysisStartResult
    {
        $this->received = $input;

        if ($this->reject) {
            throw $this->reject;
        }

        $this->analysis ??= Analysis::factory()->create();

        return new AnalysisStartResult($this->analysis, $this->reused);
    }
}
