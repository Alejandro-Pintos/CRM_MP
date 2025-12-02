<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Venta;

echo "=== VERIFICAR ESTADO DE VENTAS CON CHEQUES ===\n\n";

$ventasIds = [19, 23, 24, 25, 26, 27];

foreach ($ventasIds as $id) {
    $venta = Venta::with('cheques', 'pagos.metodoPago')->find($id);
    
    if (!$venta) {
        echo "Venta #$id: No existe\n";
        continue;
    }
    
    $chequesCount = $venta->cheques->count();
    $chequesPendientes = $venta->cheques->where('estado', 'pendiente')->count();
    $chequesCobrados = $venta->cheques->where('estado', 'cobrado')->count();
    
    echo "Venta #{$venta->id}:\n";
    echo "  Estado: {$venta->estado_pago}\n";
    echo "  Total: \${$venta->total}\n";
    echo "  Cheques: $chequesCount (Pendientes: $chequesPendientes, Cobrados: $chequesCobrados)\n";
    
    foreach ($venta->pagos as $pago) {
        echo "  - Pago: {$pago->metodoPago->nombre} \${$pago->monto}\n";
    }
    
    echo "\n";
}

echo "\n✅ Verificación completada\n";
