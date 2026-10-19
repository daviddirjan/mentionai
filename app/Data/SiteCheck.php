<?php

namespace App\Data;

use App\Enums\CheckGroup;
use App\Enums\CheckStatus;

/**
 * One technical GEO check. `evidence` is what was observed, verbatim and untranslated
 * (e.g. "GET /sitemap.xml → 404"); title/impact text comes from lang `audit.check.{key}.*` with `params`.
 */
final readonly class SiteCheck
{
    public function __construct(
        public string $key,               // e.g. robots_txt, sitemap_xml, llms_txt, html_lang, h1_count, ai_crawler_gptbot
        public CheckGroup $group,
        public CheckStatus $status,
        public string $evidence,
        /** @var array<string, scalar|null> */
        public array $params = [],
    ) {}

    /** @return array{key: string, group: string, status: string, evidence: string, params: array<string, scalar|null>} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'group' => $this->group->value, 'status' => $this->status->value, 'evidence' => $this->evidence, 'params' => $this->params];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self($data['key'], CheckGroup::from($data['group']), CheckStatus::from($data['status']), (string) $data['evidence'], $data['params'] ?? []);
    }
}
