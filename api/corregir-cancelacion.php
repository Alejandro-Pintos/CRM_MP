<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CORREGIR MONTO CANCELACIÓN VENTA #3 ===\n";

// El movimiento ID:9 es pago pero tiene monto positivo, debe ser negativo
$mov = \App\Models\MovimientoCuentaCorriente::find(9);
echo "Movimiento ID:9 - Monto actual: {$mov->monto}\n";

$mov->monto = -998250;
$mov->save();

echo "Movimiento ID:9 - Monto corregido: {$mov->monto}\n";

// Recalcular saldo
$movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)->get();
$saldo = $movimientos->sum('monto');

echo "\nSaldo calculado: {$saldo}\n";

// Actualizar cliente
$cliente = \App\Models\Cliente::find(2);
$cliente->saldo_actual = $saldo;
$cliente->save();

echo "✅ Saldo actualizado: {$saldo}\n";
echo "Crédito disponible: " . (1000000 - $saldo) . "\n";
