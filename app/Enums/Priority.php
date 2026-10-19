<?php

namespace App\Enums;

enum Priority: string
{
    case P0 = 'p0';
    case P1 = 'p1';
    case P2 = 'p2';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    /** Position in the 30/60/90 plan: P0 → days 1–30, P1 → 31–60, P2 → 61–90. */
    public function planPhase(): int
    {
        return match ($this) {
            self::P0 => 1,
            self::P1 => 2,
            self::P2 => 3,
        };
    }
}
