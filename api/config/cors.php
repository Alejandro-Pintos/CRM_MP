<?php

return [
    'paths' => ['api/*', 'storage/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:8000',
        // agrega tu dominio de producción si corresponde
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],

    // Necesario para que el frontend vea el nombre del archivo en descargas Excel
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 3600,
    'supports_credentials' => false,
];
