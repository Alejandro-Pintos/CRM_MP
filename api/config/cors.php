<?php

return [
    'paths' => ['api/*', 'storage/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter([
        'http://localhost:5173',
        'http://localhost:5174',  // Puerto alternativo cuando 5173 está ocupado
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://127.0.0.1:8000',
        // Dominio de producción desde variable de entorno
        env('FRONTEND_URL'),
        env('APP_URL'),
    ]),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],

    // Necesario para que el frontend vea el nombre del archivo en descargas Excel
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 3600,
    'supports_credentials' => false,
];
