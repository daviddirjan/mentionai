<?php

namespace App\Data;

use App\Enums\Priority;

/**
 * A ready-to-implement fix. Text comes from lang `audit.rec.{key}.{action|acceptance|why}` with `params`,
 * so it is translated and never invented. `source` is the check key or visibility rule that triggered it.
 */
final readonly class Recommendation
{
    public function __construct(
        public string $key,
        public Priority $priority,
        public string $window,            // days, e.g. "0-7", "15-30", "60-90"
        public string $source,            // e.g. "check:sitemap_xml", "visibility:local_not_recommended"
        /** @var array<string, scalar|null> */
        public array $params = [],
    ) {}

    /** @return array{key: string, priority: string, window: string, source: string, params: array<string, scalar|null>} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'priority' => $this->priority->value, 'window' => $this->window, 'source' => $this->source, 'params' => $this->params];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self($data['key'], Priority::from($data['priority']), (string) $data['window'], (string) $data['source'], $data['params'] ?? []);
    }
}
