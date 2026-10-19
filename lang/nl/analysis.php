<?php

return [
    'status' => [
        'pending' => 'In de wachtrij',
        'inspecting_website' => 'Je website wordt gelezen',
        'auditing_site' => 'Technische GEO-controles',
        'generating_questions' => 'Ontdekkingsvragen worden voorbereid',
        'querying_providers' => 'De AI-assistenten worden bevraagd',
        'analyzing' => 'De antwoorden worden geanalyseerd',
        'scoring' => 'Je score wordt berekend',
        'completed' => 'Voltooid',
        'failed' => 'Mislukt',
    ],

    'classification' => [
        'recommended' => 'Aanbevolen',
        'mentioned' => 'Genoemd, niet aanbevolen',
        'cited_only' => 'Alleen website geciteerd',
        'not_mentioned' => 'Niet genoemd',
        'uncertain' => 'Onzekere overeenkomst',
    ],

    'rejected' => [
        'captcha_failed' => 'De verificatie is mislukt. Probeer het opnieuw.',
        'rate_limited' => 'Je hebt de afgelopen tijd te veel analyses gestart. Probeer het later opnieuw.',
        'domain_limit' => 'Deze website is onlangs al geanalyseerd. Je kunt de bestaande analyse bekijken.',
        'daily_limit' => 'De dagelijkse limiet voor gratis analyses is bereikt. Probeer het morgen opnieuw.',
        'budget_exceeded' => 'Gratis analyses zijn tijdelijk niet beschikbaar. Probeer het later opnieuw.',
        'disabled' => 'Gratis analyses zijn momenteel gepauzeerd. Probeer het later opnieuw.',
        'invalid_url' => 'We konden die website niet bereiken. Controleer het adres en probeer het opnieuw.',
        'not_eligible' => 'Een GEO-audit kan alleen worden gestart vanuit een voltooide gratis check.',
    ],
];
