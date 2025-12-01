<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Pago;

// Obtener el primer cheque pendiente
$cheque = Pago::where('estado_cheque', 'pendiente')->first();

if (!$cheque) {
    echo "No hay cheques pendientes\n";
    exit;
}

echo "=== ANTES DE ACTUALIZAR ===\n";
echo "Cheque ID: {$cheque->id}\n";
echo "Número Cheque: " . ($cheque->numero_cheque ?? 'NULL') . "\n";
echo "Fecha Cheque: " . ($cheque->fecha_cheque ?? 'NULL') . "\n";
echo "Fecha Cobro: " . ($cheque->fecha_cobro ?? 'NULL') . "\n";
echo "Observaciones: " . ($cheque->observaciones_cheque ?? 'NULL') . "\n\n";

// Actualizar el cheque
$cheque->update([
    'numero_cheque' => '12345678',
    'fecha_cheque' => '2025-11-12',
    'fecha_cobro' => '2025-12-12',
    'observaciones_cheque' => 'Cheque de prueba'
]);

// Recargar desde la BD
$cheque->refresh();

echo "=== DESPUÉS DE ACTUALIZAR ===\n";
echo "Cheque ID: {$cheque->id}\n";
echo "Número Cheque: " . ($cheque->numero_cheque ?? 'NULL') . "\n";
echo "Fecha Cheque: " . ($cheque->fecha_cheque ?? 'NULL') . "\n";
echo "Fecha Cobro: " . ($cheque->fecha_cobro ?? 'NULL') . "\n";
echo "Observaciones: " . ($cheque->observaciones_cheque ?? 'NULL') . "\n";

echo "\n¡La actualización funcionó correctamente!\n";
