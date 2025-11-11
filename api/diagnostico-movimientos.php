<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';

echo "=== MOVIMIENTOS CUENTA CORRIENTE CLIENTE #2 ===\n";
$movimientos = App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)
    ->orderBy('fecha', 'asc')
    ->orderBy('id', 'asc')
    ->get();

foreach ($movimientos as $mov) {
    echo "ID: {$mov->id}\n";
    echo "Fecha: {$mov->fecha}\n";
    echo "Tipo: {$mov->tipo}\n";
    echo "Descripción: {$mov->descripcion}\n";
    echo "Debe: {$mov->debe}\n";
    echo "Haber: {$mov->haber}\n";
    echo "Monto: {$mov->monto}\n";
    echo "Venta ID: {$mov->venta_id}\n";
    echo "Pago ID: {$mov->pago_id}\n";
    echo "---\n";
}

$total_debe = $movimientos->sum('debe');
$total_haber = $movimientos->sum('haber');
$saldo_calculado = $total_debe - $total_haber;

echo "\nRESUMEN:\n";
echo "Total DEBE: {$total_debe}\n";
echo "Total HABER: {$total_haber}\n";
echo "Saldo calculado: {$saldo_calculado}\n";

// Verificar saldo del cliente
$cliente = App\Models\Cliente::find(2);
echo "Saldo cliente actual: {$cliente->saldo_actual}\n";