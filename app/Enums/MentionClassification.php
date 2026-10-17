<?php

namespace App\Enums;

enum MentionClassification: string
{
    case Recommended = 'recommended';     // explicit brand recommendation — the only class that counts toward the score
    case Mentioned = 'mentioned';         // brand named, but not recommended
    case CitedOnly = 'cited_only';        // brand domain appears only among citations
    case NotMentioned = 'not_mentioned';
    case Uncertain = 'uncertain';         // ambiguous match; never counted as positive

    public function countsAsRecommendation(): bool
    {
        return $this === self::Recommended;
    }

    public function translationKey(): string
    {
        return 'analysis.classification.'.$this->value;
    }
}
