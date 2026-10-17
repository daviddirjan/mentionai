<?php

return [
    'status' => [
        'pending' => 'En file d’attente',
        'inspecting_website' => 'Lecture de votre site web',
        'generating_questions' => 'Préparation des questions de découverte',
        'querying_providers' => 'Interrogation des assistants IA',
        'analyzing' => 'Analyse des réponses',
        'scoring' => 'Calcul de votre score',
        'completed' => 'Terminé',
        'failed' => 'Échec',
    ],

    'classification' => [
        'recommended' => 'Recommandé',
        'mentioned' => 'Mentionné, non recommandé',
        'cited_only' => 'Site web cité uniquement',
        'not_mentioned' => 'Non mentionné',
        'uncertain' => 'Correspondance incertaine',
    ],

    'rejected' => [
        'captcha_failed' => 'La vérification a échoué. Veuillez réessayer.',
        'rate_limited' => 'Vous avez lancé trop d’analyses récemment. Veuillez réessayer plus tard.',
        'domain_limit' => 'Ce site web a déjà été analysé récemment. Vous pouvez consulter l’analyse existante.',
        'daily_limit' => 'La limite quotidienne d’analyses gratuites est atteinte. Veuillez réessayer demain.',
        'budget_exceeded' => 'Les analyses gratuites sont temporairement indisponibles. Veuillez réessayer plus tard.',
        'disabled' => 'Les analyses gratuites sont actuellement suspendues. Veuillez réessayer plus tard.',
        'invalid_url' => 'Nous n’avons pas pu accéder à ce site web. Vérifiez l’adresse et réessayez.',
    ],
];
