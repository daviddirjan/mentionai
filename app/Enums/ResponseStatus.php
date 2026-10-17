<?php

namespace App\Enums;

enum ResponseStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped'; // provider disabled or a spending limit was reached

    public function isFinished(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Skipped], true);
    }
}
