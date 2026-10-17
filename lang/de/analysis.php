<?php

return [
    'status' => [
        'pending' => 'In der Warteschlange',
        'inspecting_website' => 'Ihre Website wird gelesen',
        'generating_questions' => 'Entdeckungsfragen werden vorbereitet',
        'querying_providers' => 'KI-Assistenten werden befragt',
        'analyzing' => 'Antworten werden analysiert',
        'scoring' => 'Ihr Score wird berechnet',
        'completed' => 'Abgeschlossen',
        'failed' => 'Fehlgeschlagen',
    ],

    'classification' => [
        'recommended' => 'Empfohlen',
        'mentioned' => 'Erwähnt, nicht empfohlen',
        'cited_only' => 'Nur Website zitiert',
        'not_mentioned' => 'Nicht erwähnt',
        'uncertain' => 'Unsichere Zuordnung',
    ],

    'rejected' => [
        'captcha_failed' => 'Die Überprüfung ist fehlgeschlagen. Bitte versuchen Sie es erneut.',
        'rate_limited' => 'Sie haben zu viele Analysen in kurzer Zeit gestartet. Bitte versuchen Sie es später erneut.',
        'domain_limit' => 'Diese Website wurde kürzlich bereits analysiert. Sie können die bestehende Analyse ansehen.',
        'daily_limit' => 'Das Tageslimit für kostenlose Analysen ist erreicht. Bitte versuchen Sie es morgen erneut.',
        'budget_exceeded' => 'Kostenlose Analysen sind vorübergehend nicht verfügbar. Bitte versuchen Sie es später erneut.',
        'disabled' => 'Kostenlose Analysen sind derzeit pausiert. Bitte versuchen Sie es später erneut.',
        'invalid_url' => 'Wir konnten diese Website nicht erreichen. Prüfen Sie die Adresse und versuchen Sie es erneut.',
    ],
];
