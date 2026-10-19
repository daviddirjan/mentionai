<?php

namespace App\Data;

/**
 * Verdict, executive summary and proposed H1/title/meta, written in the analysis language.
 * source: "ai" (one grounded provider call), "rules" (deterministic fallback after an AI failure), "demo" (mock mode).
 */
final readonly class AuditNarrative
{
    public function __construct(
        public string $verdict,
        /** @var list<string> */
        public array $summary,
        public ?string $h1,
        public ?string $title,
        public ?string $metaDescription,
        public string $source,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['verdict' => $this->verdict, 'summary' => $this->summary, 'h1' => $this->h1, 'title' => $this->title, 'meta_description' => $this->metaDescription, 'source' => $this->source];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self((string) $data['verdict'], array_values($data['summary'] ?? []), $data['h1'] ?? null, $data['title'] ?? null, $data['meta_description'] ?? null, (string) ($data['source'] ?? 'rules'));
    }
}
