<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFICAR VENTA #13 ===\n";

$venta = \App\Models\Venta::with('pagos.metodoPago')->find(13);

if ($venta) {
    echo "Total venta: {$venta->total}\n";
    echo "Estado pago: {$venta->estado_pago}\n";
    echo "\nPagos:\n";
    
    foreach($venta->pagos as $pago) {
        echo "- ID:{$pago->id} | Método:{$pago->metodoPago->nombre} | Monto:{$pago->monto} | Estado cheque:" . ($pago->estado_cheque ?? 'NULL') . "\n";
    }
} else {
    echo "Venta no encontrada\n";
}
