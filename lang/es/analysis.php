<?php

return [
    'status' => [
        'pending' => 'En cola',
        'inspecting_website' => 'Leyendo tu sitio web',
        'auditing_site' => 'Comprobaciones técnicas GEO',
        'generating_questions' => 'Preparando las preguntas de descubrimiento',
        'querying_providers' => 'Consultando a los asistentes de IA',
        'analyzing' => 'Analizando las respuestas',
        'scoring' => 'Calculando tu puntuación',
        'completed' => 'Completado',
        'failed' => 'Fallido',
    ],

    'classification' => [
        'recommended' => 'Recomendado',
        'mentioned' => 'Mencionado, no recomendado',
        'cited_only' => 'Solo se cita el sitio web',
        'not_mentioned' => 'No mencionado',
        'uncertain' => 'Coincidencia incierta',
    ],

    'rejected' => [
        'captcha_failed' => 'La verificación ha fallado. Inténtalo de nuevo.',
        'rate_limited' => 'Has iniciado demasiados análisis recientemente. Inténtalo de nuevo más tarde.',
        'domain_limit' => 'Este sitio web ya se analizó recientemente. Puedes ver el análisis existente.',
        'daily_limit' => 'Hemos alcanzado el límite diario de análisis gratuitos. Inténtalo de nuevo mañana.',
        'budget_exceeded' => 'Los análisis gratuitos no están disponibles temporalmente. Inténtalo de nuevo más tarde.',
        'disabled' => 'Los análisis gratuitos están en pausa. Inténtalo de nuevo más tarde.',
        'invalid_url' => 'No hemos podido acceder a ese sitio web. Revisa la dirección e inténtalo de nuevo.',
        'not_eligible' => 'Una auditoría GEO solo puede iniciarse desde una comprobación gratuita completada.',
    ],
];
