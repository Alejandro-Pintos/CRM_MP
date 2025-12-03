<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\Usuario;

echo "=== TEST ENDPOINT MÉTODOS DE PAGO ===\n\n";

// 1. Generar token JWT
$admin = Usuario::where('email', 'admin@example.com')->first();

if (!$admin) {
    echo "❌ Usuario admin no encontrado\n";
    exit(1);
}

$token = auth('api')->login($admin);

echo "1. Token generado: " . substr($token, 0, 20) . "...\n\n";

// 2. Simular request al endpoint
$ch = curl_init('http://localhost/api/v1/metodos-pago');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "2. HTTP Status: {$httpCode}\n";
echo "3. Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT) . "\n\n";

if ($httpCode === 200) {
    echo "✅ Endpoint funcionando correctamente\n";
    $data = json_decode($response, true);
    echo "   Total métodos de pago: " . (is_array($data) ? count($data) : 0) . "\n";
} else {
    echo "❌ Endpoint con error\n";
}

echo "\n=== FIN TEST ===\n";
