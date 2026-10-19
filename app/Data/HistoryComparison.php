<?php

namespace App\Data;

use Carbon\CarbonInterface;

/** Before/after against the previous completed analysis with the same company, tier, market, language and location. */
final readonly class HistoryComparison
{
    public function __construct(
        public string $previousUuid,
        public CarbonInterface $previousDate,
        public ?float $scoreBefore,
        public ?float $scoreAfter,
        /** @var array<string, array{before: ?float, after: ?float}> keyed by provider slug */
        public array $providers,
        /** @var list<string> */
        public array $competitorsGained,
        /** @var list<string> */
        public array $competitorsLost,
        /** @var list<string> check keys that went from warn/fail to pass (audits only) */
        public array $checksFixed,
        /** @var list<string> check keys that went from pass to warn/fail (audits only) */
        public array $checksRegressed,
    ) {}
}
