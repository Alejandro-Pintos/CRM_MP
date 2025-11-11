<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ELIMINAR MOVIMIENTO DUPLICADO ===\n";

// Eliminar el movimiento duplicado ID:15 con monto 0
$eliminado = \App\Models\MovimientoCuentaCorriente::where('id', 15)->delete();
echo "Movimiento ID:15 eliminado: {$eliminado}\n";

// Recalcular saldo basado en montos
$movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)->get();
$saldo = $movimientos->sum('monto');

echo "Saldo calculado: {$saldo}\n";

// Actualizar cliente
$cliente = \App\Models\Cliente::find(2);
$cliente->saldo_actual = $saldo;
$cliente->save();

echo "✅ Saldo actualizado: {$saldo}\n";
echo "Crédito disponible: " . (1000000 - $saldo) . "\n";
