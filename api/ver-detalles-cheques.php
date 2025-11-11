<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DATOS COMPLETOS DE CHEQUES PENDIENTES ===\n";

$cheques = \App\Models\Pago::with('metodoPago', 'venta.cliente')
    ->where('estado_cheque', 'pendiente')
    ->get();

foreach($cheques as $ch) {
    echo "\n--- Cheque ID: {$ch->id} ---\n";
    echo "Venta: #{$ch->venta_id}\n";
    echo "Cliente: {$ch->venta->cliente->nombre} {$ch->venta->cliente->apellido}\n";
    echo "Monto: \${$ch->monto}\n";
    echo "Estado: {$ch->estado_cheque}\n";
    echo "Número cheque: " . ($ch->numero_cheque ?? 'NULL') . "\n";
    echo "Fecha cheque: " . ($ch->fecha_cheque ?? 'NULL') . "\n";
    echo "Fecha cobro: " . ($ch->fecha_cobro ?? 'NULL') . "\n";
    echo "Observaciones: " . ($ch->observaciones_cheque ?? 'NULL') . "\n";
}
