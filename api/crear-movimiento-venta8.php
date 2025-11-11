<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CREAR MOVIMIENTO VENTA #8 ===\n";

try {
    $venta = \App\Models\Venta::find(8);
    
    if (!$venta) {
        echo "❌ Venta #8 no encontrada\n";
        exit;
    }
    
    echo "Venta #8: Total " . $venta->total_venta . ", Cliente ID " . $venta->cliente_id . "\n";
    
    // Verificar si ya existe el movimiento
    $existe = \App\Models\MovimientoCuentaCorriente::where('venta_id', 8)
        ->where('tipo', 'venta')
        ->exists();
    
    if ($existe) {
        echo "⚠️ El movimiento ya existe\n";
        exit;
    }
    
    // Crear movimiento
    \App\Models\MovimientoCuentaCorriente::create([
        'cliente_id' => $venta->cliente_id,
        'venta_id' => $venta->id,
        'tipo' => 'venta',
        'referencia_id' => $venta->id,
        'monto' => abs($venta->total_venta),
        'debe' => abs($venta->total_venta),
        'haber' => 0,
        'fecha' => $venta->fecha,
        'descripcion' => "Saldo pendiente venta #" . $venta->id,
    ]);
    
    echo "✅ Movimiento creado correctamente\n";
    
    // Actualizar saldo del cliente
    $cliente = $venta->cliente;
    $cliente->saldo_actual = ($cliente->saldo_actual ?? 0) - abs($venta->total_venta);
    $cliente->save();
    
    echo "Saldo cliente actualizado: " . $cliente->saldo_actual . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
