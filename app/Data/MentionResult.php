<?php

namespace App\Data;

use App\Enums\MentionClassification;

final readonly class MentionResult
{
    /**
     * @param  list<string>  $matchedTerms
     * @param  list<array{type: string, term: string, snippet: string}>  $evidence
     */
    public function __construct(
        public MentionClassification $classification,
        public array $matchedTerms = [],
        public array $evidence = [],
    ) {}
}
