<?php

namespace App\Data;

/**
 * Validated public form input. Built by the frontend (Livewire form) and passed to AnalysisStarter.
 * $websiteUrl is the raw user URL; normalization happens in the starter via App\Support\Domain.
 */
final readonly class AnalysisInput
{
    public function __construct(
        public string $companyName,
        public string $websiteUrl,
        public string $market,          // ISO 3166-1 alpha-2, e.g. "RO"
        public string $language,        // analysis language code, one of config('mentionai.analysis_languages')
        public ?string $industry,
        public string $interfaceLocale, // the UI locale the visitor is browsing in
        public string $ip,
        public ?string $turnstileToken = null,
    ) {}
}
