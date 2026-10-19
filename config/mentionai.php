<?php

return [

    /*
    | AI mode: "live" calls real providers; "mock" uses deterministic demo adapters (no paid calls).
    | Live mode never silently falls back to mock. Mock results are flagged is_demo and labeled DEMO.
    */
    'ai_mode' => env('MENTIONAI_AI_MODE', 'mock'),

    // API keys are read only from the environment. Never store them in the database or expose them.
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'default_model' => env('OPENAI_MODEL', 'gpt-5-mini'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'default_model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'default_model' => env('ANTHROPIC_MODEL', 'claude-haiku-5-5'),
        ],
        'perplexity' => [
            'api_key' => env('PERPLEXITY_API_KEY'),
            'base_url' => env('PERPLEXITY_BASE_URL', 'https://api.perplexity.ai'),
            'default_model' => env('PERPLEXITY_MODEL', 'fast'),
        ],
    ],

    // Interface locales (URL prefix /{locale}). Add a locale here plus lang/{locale}/*.php to support a new language.
    'locales' => ['en', 'ro', 'es', 'fr', 'de', 'it', 'pt', 'nl', 'pl'],

    // Languages an analysis can be run in. Independent from the interface locale.
    'analysis_languages' => ['en', 'ro', 'es', 'fr', 'de', 'it', 'pt', 'nl', 'pl'],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'admin_email' => env('MENTIONAI_ADMIN_EMAIL', env('MAIL_FROM_ADDRESS')),

    'website_inspection' => [
        'timeout_seconds' => 8,
        'max_bytes' => 1_500_000,
        'max_redirects' => 3,
        'user_agent' => 'MentionAIBot/1.0 (+https://mentionai.com)',
    ],

    /*
    | Defaults for admin-editable settings. Read them through App\Support\Settings, which
    | overlays values saved in the `settings` table on top of these.
    */
    'defaults' => [
        'site_name' => 'MentionAI',
        'tagline' => 'Discover how AI sees your brand.',
        'contact_email' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'company_info' => 'Axiontic SRL',
        'social_links' => [],
        'logo_path' => null,
        'favicon_path' => null,
        'seo_title' => 'MentionAI — Discover how AI sees your brand',
        'meta_description' => 'Check whether ChatGPT, Gemini, Claude and Perplexity recommend your business to potential customers.',
        'default_locale' => 'en',
        'maintenance_mode' => false,

        'analyses_enabled' => true,         // global enable/disable switch for paid requests
        'query_count' => 3,                 // discovery questions per analysis
        'max_analyses_per_day' => 200,
        'max_cost_per_analysis' => 0.50,    // USD estimate
        'monthly_budget' => 100.00,         // USD estimate
        'free_analyses_per_domain' => 1,
        'domain_window_hours' => 24,
        'ip_analyses_per_hour' => 5,
        'cache_hours' => 24,                // reuse identical analyses younger than this

        'audit_enabled' => true,            // GEO audit (tier audit) after a lead leaves an email
        'audit_query_count' => 25,          // discovery questions per audit (x enabled providers = tests)
        'max_cost_per_audit' => 2.00,       // USD estimate
        'audit_narrative_provider' => 'openai', // provider slug for the one verdict/summary call
        'report_link_days' => 30,           // validity of the signed PDF link sent by email

        'data_retention_days' => 365,

        'report_price' => 9900,             // minor units; used once payments ship
        'report_currency' => 'RON',
    ],
];
