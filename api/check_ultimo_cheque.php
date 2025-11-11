<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Buscar el último pago con método cheque
$pago = \App\Models\Pago::with('metodoPago')
    ->whereHas('metodoPago', function($q) {
        $q->where('nombre', 'Cheque');
    })
    ->latest()
    ->first();

if ($pago) {
    echo "=== ÚLTIMO PAGO CON CHEQUE ===\n";
    echo "ID: {$pago->id}\n";
    echo "Venta ID: {$pago->venta_id}\n";
    echo "Monto: {$pago->monto}\n";
    echo "Estado cheque: " . ($pago->estado_cheque ?? 'NULL') . "\n";
    echo "Número cheque: " . ($pago->numero_cheque ?? 'NULL') . "\n";
    echo "Fecha pago: {$pago->fecha_pago}\n";
    
    // Verificar estado de la venta
    $venta = $pago->venta;
    echo "\nVenta #{$venta->id}:\n";
    echo "Total: {$venta->total}\n";
    echo "Estado pago: {$venta->estado_pago}\n";
} else {
    echo "No se encontraron pagos con cheque\n";
}
