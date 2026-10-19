<?php

namespace App\Enums;

/** Search intent of a discovery question; used to group visibility in the audit report. */
enum QuestionCategory: string
{
    case Top = 'top';
    case Service = 'service';
    case Local = 'local';
    case Compare = 'compare';
    case Problem = 'problem';
    case Budget = 'budget';
    case Alternatives = 'alternatives';
    case Reviews = 'reviews';
    case Specialist = 'specialist';
    case B2b = 'b2b';

    public function translationKey(): string
    {
        return 'audit.category.'.$this->value;
    }
}
