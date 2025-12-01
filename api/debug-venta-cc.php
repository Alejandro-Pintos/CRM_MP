<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Venta;
use App\Models\Pago;
use App\Models\MovimientoCuentaCorriente;
use App\Models\MetodoPago;

echo "=== DEBUG VENTA CUENTA CORRIENTE ===\n\n";

// Obtener última venta
$venta = Venta::latest()->first();

if (!$venta) {
    echo "No hay ventas en el sistema\n";
    exit;
}

echo "VENTA ID: {$venta->id}\n";
echo "Total: \${$venta->total}\n";
echo "Estado: {$venta->estado_pago}\n\n";

// Obtener pagos
echo "PAGOS:\n";
$pagos = Pago::where('venta_id', $venta->id)->get();
$totalPagos = 0;
foreach ($pagos as $pago) {
    $metodo = MetodoPago::find($pago->metodo_pago_id);
    echo "- Método: {$metodo->nombre}, Monto: \${$pago->monto}\n";
    
    // Verificar si es cuenta corriente
    $esCuentaCorriente = strtolower($metodo->nombre) === 'cuenta corriente';
    echo "  Es CC: " . ($esCuentaCorriente ? 'SÍ' : 'NO') . "\n";
    
    if (!$esCuentaCorriente) {
        $totalPagos += $pago->monto;
    }
}

echo "\nTOTAL PAGOS (sin CC): \${$totalPagos}\n";
echo "SALDO PENDIENTE: \$" . ($venta->total - $totalPagos) . "\n\n";

// Obtener movimientos
echo "MOVIMIENTOS CC:\n";
$movimientos = MovimientoCuentaCorriente::where('referencia_id', $venta->id)
    ->where('tipo', 'venta')
    ->get();

foreach ($movimientos as $mov) {
    echo "- ID: {$mov->id}\n";
    echo "  Debe: \${$mov->debe}\n";
    echo "  Haber: \${$mov->haber}\n";
    echo "  Monto: \${$mov->monto}\n";
    echo "  Descripción: {$mov->descripcion}\n";
}

echo "\n=== FIN DEBUG ===\n";
