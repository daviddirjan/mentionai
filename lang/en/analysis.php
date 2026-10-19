<?php

return [
    'status' => [
        'pending' => 'Queued',
        'inspecting_website' => 'Reading your website',
        'auditing_site' => 'Running technical GEO checks',
        'generating_questions' => 'Preparing discovery questions',
        'querying_providers' => 'Asking the AI assistants',
        'analyzing' => 'Analyzing the answers',
        'scoring' => 'Calculating your score',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],

    'classification' => [
        'recommended' => 'Recommended',
        'mentioned' => 'Mentioned, not recommended',
        'cited_only' => 'Website cited only',
        'not_mentioned' => 'Not mentioned',
        'uncertain' => 'Uncertain match',
    ],

    'rejected' => [
        'captcha_failed' => 'The verification challenge failed. Please try again.',
        'rate_limited' => 'You have started too many analyses recently. Please try again later.',
        'domain_limit' => 'This website has already been analyzed recently. You can view the existing analysis.',
        'daily_limit' => 'We have reached the daily limit of free analyses. Please try again tomorrow.',
        'budget_exceeded' => 'Free analyses are temporarily unavailable. Please try again later.',
        'disabled' => 'Free analyses are currently paused. Please try again later.',
        'invalid_url' => 'We could not reach that website. Check the address and try again.',
        'not_eligible' => 'A GEO audit can only be started from a completed free check.',
    ],
];
