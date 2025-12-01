<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Pago;

$cheques = Pago::where('estado_cheque', 'pendiente')
    ->with('venta.cliente')
    ->get();

echo "Total cheques pendientes: " . $cheques->count() . "\n\n";

foreach ($cheques as $cheque) {
    echo "=== Cheque ID: {$cheque->id} ===\n";
    echo "Venta ID: {$cheque->venta_id}\n";
    echo "Número Cheque: " . ($cheque->numero_cheque ?? 'NULL') . "\n";
    echo "Fecha Cheque: " . ($cheque->fecha_cheque ?? 'NULL') . "\n";
    echo "Fecha Cobro: " . ($cheque->fecha_cobro ?? 'NULL') . "\n";
    echo "Observaciones: " . ($cheque->observaciones_cheque ?? 'NULL') . "\n";
    echo "Estado: {$cheque->estado_cheque}\n";
    echo "Cliente: {$cheque->venta->cliente->nombre} {$cheque->venta->cliente->apellido}\n";
    echo "\n";
}
