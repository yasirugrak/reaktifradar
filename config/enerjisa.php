<?php

return [
    'notifications_enabled' => (bool) env('REAKTIFRADAR_NOTIFICATIONS_ENABLED', false),
    // Scoped diagnostics: never include credentials, raw URLs, bodies or headers.
    'telegram' => [
        'token' => env('ENERJISA_TELEGRAM_BOT_TOKEN'),
        'username' => env('ENERJISA_TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('ENERJISA_TELEGRAM_WEBHOOK_SECRET'),
    ],
    'debug' => (bool) env('ENERJISA_DEBUG', false),
];
