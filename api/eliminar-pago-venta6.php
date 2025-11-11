<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ELIMINAR PAGO INCORRECTO VENTA #6 ===\n";

// El movimiento ID:11 es un pago de venta #6 que no debería existir
// porque estamos en cuenta corriente del cliente #2 y esa venta no es de cuenta corriente
$mov = \App\Models\MovimientoCuentaCorriente::find(11);
echo "Movimiento a eliminar: ID:{$mov->id}, Desc: {$mov->descripcion}, Monto: {$mov->monto}\n";

$mov->delete();
echo "✅ Movimiento eliminado\n";

// Recalcular saldo
$movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)->get();
$saldo = $movimientos->sum('monto');

echo "\nSaldo calculado: {$saldo}\n";

// Actualizar cliente
$cliente = \App\Models\Cliente::find(2);
$cliente->saldo_actual = $saldo;
$cliente->save();

echo "✅ Saldo del cliente actualizado: {$saldo}\n";
echo "Crédito disponible: " . (1000000 - $saldo) . "\n";
