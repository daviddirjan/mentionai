<?php

return [
    'status' => [
        'pending' => 'Em fila',
        'inspecting_website' => 'A ler o seu site',
        'generating_questions' => 'A preparar as perguntas de descoberta',
        'querying_providers' => 'A consultar os assistentes de IA',
        'analyzing' => 'A analisar as respostas',
        'scoring' => 'A calcular a sua pontuação',
        'completed' => 'Concluída',
        'failed' => 'Falhou',
    ],

    'classification' => [
        'recommended' => 'Recomendado',
        'mentioned' => 'Mencionado, não recomendado',
        'cited_only' => 'Apenas o site foi citado',
        'not_mentioned' => 'Não mencionado',
        'uncertain' => 'Correspondência incerta',
    ],

    'rejected' => [
        'captcha_failed' => 'A verificação falhou. Tente novamente.',
        'rate_limited' => 'Iniciou demasiadas análises recentemente. Tente novamente mais tarde.',
        'domain_limit' => 'Este site já foi analisado recentemente. Pode ver a análise existente.',
        'daily_limit' => 'Atingimos o limite diário de análises gratuitas. Tente novamente amanhã.',
        'budget_exceeded' => 'As análises gratuitas estão temporariamente indisponíveis. Tente novamente mais tarde.',
        'disabled' => 'As análises gratuitas estão atualmente em pausa. Tente novamente mais tarde.',
        'invalid_url' => 'Não conseguimos aceder a esse site. Verifique o endereço e tente novamente.',
    ],
];
