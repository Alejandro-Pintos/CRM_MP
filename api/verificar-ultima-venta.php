<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFICACIÓN MOVIMIENTOS CUENTA CORRIENTE ===\n\n";

// Obtener la última venta
$ultimaVenta = \App\Models\Venta::with(['cliente', 'pagos'])->latest()->first();

if (!$ultimaVenta) {
    echo "No hay ventas registradas\n";
    exit;
}

echo "ÚLTIMA VENTA:\n";
echo "ID: {$ultimaVenta->id}\n";
echo "Cliente: {$ultimaVenta->cliente->nombre} {$ultimaVenta->cliente->apellido}\n";
echo "Total: \${$ultimaVenta->total}\n";
echo "Estado Pago: {$ultimaVenta->estado_pago}\n";
echo "Fecha: {$ultimaVenta->fecha}\n\n";

echo "PAGOS DE ESTA VENTA:\n";
foreach ($ultimaVenta->pagos as $pago) {
    echo "- ID: {$pago->id}, Método: {$pago->metodoPago->nombre}, Monto: \${$pago->monto}\n";
}

echo "\nMOVIMIENTOS CC DEL CLIENTE #{$ultimaVenta->cliente_id}:\n";
$movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', $ultimaVenta->cliente_id)
    ->orderBy('id', 'desc')
    ->get();

if ($movimientos->count() === 0) {
    echo "❌ NO HAY MOVIMIENTOS REGISTRADOS\n";
} else {
    foreach ($movimientos as $mov) {
        echo "- ID: {$mov->id}, Tipo: {$mov->tipo}, Ref: {$mov->referencia_id}, Debe: {$mov->debe}, Haber: {$mov->haber}, Desc: {$mov->descripcion}\n";
    }
}

echo "\nSALDO CLIENTE:\n";
echo "Saldo actual en BD: \${$ultimaVenta->cliente->saldo_actual}\n";