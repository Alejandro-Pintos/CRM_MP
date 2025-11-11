<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';

$cliente = App\Models\Cliente::find(2);
echo "Cliente: {$cliente->nombre} {$cliente->apellido}\n";
echo "Saldo actual: {$cliente->saldo_actual}\n";
echo "Limite credito: {$cliente->limite_credito}\n";

// Calcular saldo desde movimientos
$movimientos = App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)->get();
$total_debe = $movimientos->sum('debe');
$total_haber = $movimientos->sum('haber');
$saldo_calculado = $total_debe - $total_haber;

echo "Total debe (ventas): {$total_debe}\n";
echo "Total haber (pagos): {$total_haber}\n";
echo "Saldo calculado: {$saldo_calculado}\n";
echo "Diferencia: " . ($cliente->saldo_actual - $saldo_calculado) . "\n";