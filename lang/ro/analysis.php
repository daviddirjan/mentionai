<?php

return [
    'status' => [
        'pending' => 'În coadă',
        'inspecting_website' => 'Citim site-ul tău',
        'generating_questions' => 'Pregătim întrebările de descoperire',
        'querying_providers' => 'Întrebăm asistenții AI',
        'analyzing' => 'Analizăm răspunsurile',
        'scoring' => 'Calculăm scorul',
        'completed' => 'Finalizat',
        'failed' => 'Eșuat',
    ],

    'classification' => [
        'recommended' => 'Recomandat',
        'mentioned' => 'Menționat, nerecomandat',
        'cited_only' => 'Doar site-ul citat',
        'not_mentioned' => 'Nemenționat',
        'uncertain' => 'Potrivire incertă',
    ],

    'rejected' => [
        'captcha_failed' => 'Verificarea a eșuat. Încearcă din nou.',
        'rate_limited' => 'Ai pornit prea multe analize recent. Încearcă din nou mai târziu.',
        'domain_limit' => 'Acest site a fost analizat recent. Poți vedea analiza existentă.',
        'daily_limit' => 'Am atins limita zilnică de analize gratuite. Încearcă din nou mâine.',
        'budget_exceeded' => 'Analizele gratuite sunt temporar indisponibile. Încearcă din nou mai târziu.',
        'disabled' => 'Analizele gratuite sunt momentan suspendate. Încearcă din nou mai târziu.',
        'invalid_url' => 'Nu am putut accesa acest site. Verifică adresa și încearcă din nou.',
    ],
];
