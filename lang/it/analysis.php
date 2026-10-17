<?php

return [
    'status' => [
        'pending' => 'In coda',
        'inspecting_website' => 'Lettura del tuo sito web',
        'generating_questions' => 'Preparazione delle domande di scoperta',
        'querying_providers' => 'Interrogazione degli assistenti IA',
        'analyzing' => 'Analisi delle risposte',
        'scoring' => 'Calcolo del punteggio',
        'completed' => 'Completata',
        'failed' => 'Non riuscita',
    ],

    'classification' => [
        'recommended' => 'Consigliato',
        'mentioned' => 'Menzionato, non consigliato',
        'cited_only' => 'Solo sito web citato',
        'not_mentioned' => 'Non menzionato',
        'uncertain' => 'Corrispondenza incerta',
    ],

    'rejected' => [
        'captcha_failed' => 'La verifica non è riuscita. Riprova.',
        'rate_limited' => 'Hai avviato troppe analisi di recente. Riprova più tardi.',
        'domain_limit' => 'Questo sito web è già stato analizzato di recente. Puoi vedere l’analisi esistente.',
        'daily_limit' => 'Abbiamo raggiunto il limite giornaliero di analisi gratuite. Riprova domani.',
        'budget_exceeded' => 'Le analisi gratuite non sono temporaneamente disponibili. Riprova più tardi.',
        'disabled' => 'Le analisi gratuite sono attualmente sospese. Riprova più tardi.',
        'invalid_url' => 'Non siamo riusciti a raggiungere quel sito web. Controlla l’indirizzo e riprova.',
    ],
];
