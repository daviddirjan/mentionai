<?php

namespace App\Enums;

enum ContactMessageStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Replied = 'replied';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::InProgress => 'In Progress',
            self::Replied => 'Replied',
            self::Archived => 'Archived',
        };
    }
}
