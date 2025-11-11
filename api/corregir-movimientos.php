<?php
require 'vendor/autoload.php';

// Inicializar Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CORRECCIÓN MOVIMIENTOS VENTA #8 ===\n";

try {
    // Buscar movimientos duplicados de venta #8
    $movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)
        ->where('descripcion', 'LIKE', '%venta #8%')
        ->orWhere('descripcion', 'LIKE', '%venta%8%')
        ->get();
    
    echo "Movimientos encontrados de venta #8: {$movimientos->count()}\n";
    
    foreach ($movimientos as $mov) {
        echo "ID: {$mov->id}, Tipo: {$mov->tipo}, Desc: {$mov->descripcion}, Debe: {$mov->debe}, Haber: {$mov->haber}\n";
    }
    
    // Eliminar movimientos duplicados (mantener solo el de venta, eliminar pagos)
    $eliminados = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)
        ->where('tipo', 'venta')
        ->where('descripcion', 'LIKE', '%Saldo pendiente%venta #8%')
        ->where('debe', 0) // El duplicado tiene debe=0
        ->delete();
    
    echo "\nMovimientos duplicados eliminados: {$eliminados}\n";
    
    // Recalcular saldo
    $todos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)->get();
    $total_debe = $todos->sum('debe');
    $total_haber = $todos->sum('haber');
    $saldo = $total_haber - $total_debe;
    
    echo "\nRESUMEN:\n";
    echo "Total DEBE: {$total_debe}\n";
    echo "Total HABER: {$total_haber}\n";
    echo "Saldo calculado: {$saldo}\n";
    
    // Actualizar cliente
    $cliente = \App\Models\Cliente::find(2);
    $cliente->saldo_actual = $saldo;
    $cliente->save();
    
    echo "✅ Cliente actualizado con saldo: {$saldo}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
