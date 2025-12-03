<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\MetodoPago;

echo "=== MÉTODOS DE PAGO EN BASE DE DATOS ===\n\n";

$metodos = MetodoPago::all();

if ($metodos->isEmpty()) {
    echo "❌ NO HAY MÉTODOS DE PAGO EN LA BASE DE DATOS\n";
    echo "   Esto es CRÍTICO - el sistema no puede funcionar sin métodos de pago\n\n";
    echo "💡 Solución: Ejecutar seeder de métodos de pago\n";
} else {
    echo "✅ Métodos de pago encontrados: {$metodos->count()}\n\n";
    foreach ($metodos as $metodo) {
        $estado = $metodo->estado === 'activo' ? '✅' : '❌';
        echo "   {$estado} ID: {$metodo->id} - {$metodo->nombre} ({$metodo->estado})\n";
    }
}

echo "\n=== FIN VERIFICACIÓN ===\n";
