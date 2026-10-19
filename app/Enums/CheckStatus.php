<?php

namespace App\Enums;

enum CheckStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
    case Info = 'info';     // observed fact without a verdict (e.g. response time)

    public function needsAction(): bool
    {
        return in_array($this, [self::Warn, self::Fail], true);
    }
}
