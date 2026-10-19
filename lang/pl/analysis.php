<?php

return [
    'status' => [
        'pending' => 'W kolejce',
        'inspecting_website' => 'Czytanie Twojej strony',
        'auditing_site' => 'Techniczne kontrole GEO',
        'generating_questions' => 'Przygotowywanie pytań odkrywczych',
        'querying_providers' => 'Pytanie asystentów AI',
        'analyzing' => 'Analiza odpowiedzi',
        'scoring' => 'Obliczanie wyniku',
        'completed' => 'Zakończono',
        'failed' => 'Niepowodzenie',
    ],

    'classification' => [
        'recommended' => 'Polecany',
        'mentioned' => 'Wspomniany, niepolecany',
        'cited_only' => 'Zacytowano tylko stronę',
        'not_mentioned' => 'Niewspomniany',
        'uncertain' => 'Niepewne dopasowanie',
    ],

    'rejected' => [
        'captcha_failed' => 'Weryfikacja nie powiodła się. Spróbuj ponownie.',
        'rate_limited' => 'W ostatnim czasie uruchomiono zbyt wiele analiz. Spróbuj ponownie później.',
        'domain_limit' => 'Ta strona została niedawno przeanalizowana. Możesz zobaczyć istniejącą analizę.',
        'daily_limit' => 'Osiągnięto dzienny limit bezpłatnych analiz. Spróbuj ponownie jutro.',
        'budget_exceeded' => 'Bezpłatne analizy są tymczasowo niedostępne. Spróbuj ponownie później.',
        'disabled' => 'Bezpłatne analizy są obecnie wstrzymane. Spróbuj ponownie później.',
        'invalid_url' => 'Nie udało się połączyć z tą stroną. Sprawdź adres i spróbuj ponownie.',
        'not_eligible' => 'Audyt GEO można rozpocząć tylko z ukończonej bezpłatnej analizy.',
    ],
];
