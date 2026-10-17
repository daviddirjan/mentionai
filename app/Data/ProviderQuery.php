<?php

namespace App\Data;

/** One question sent to one provider. Language/market let adapters set locale hints where supported. */
final readonly class ProviderQuery
{
    public function __construct(
        public string $question,
        public string $language,
        public string $market,
    ) {}
}
