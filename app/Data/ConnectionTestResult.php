<?php

namespace App\Data;

final readonly class ConnectionTestResult
{
    public function __construct(
        public bool $ok,
        public string $message,
        public int $durationMs = 0,
    ) {}
}
