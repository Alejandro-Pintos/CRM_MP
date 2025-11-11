<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CORRECCIÓN SALDO ALEJANDRO ===\n\n";

// 1. Eliminar movimientos huérfanos (sin debe ni haber)
$movimientosHuerfanos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)
    ->where('debe', 0)
    ->where('haber', 0)
    ->get();

echo "Movimientos huérfanos encontrados: " . $movimientosHuerfanos->count() . "\n";
foreach ($movimientosHuerfanos as $mov) {
    echo "  - ID: {$mov->id} | {$mov->descripcion}\n";
    $mov->delete();
}

// 2. Recalcular saldo
$cliente = \App\Models\Cliente::find(2);
$movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)->get();

$total_debe = $movimientos->sum('debe');
$total_haber = $movimientos->sum('haber');
$saldo_correcto = $total_debe - $total_haber;

echo "\nSaldo correcto: {$saldo_correcto}\n";

$cliente->saldo_actual = $saldo_correcto;
$cliente->save();

echo "✅ Saldo actualizado\n";