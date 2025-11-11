<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CORRECCIÓN DE MOVIMIENTOS - CLIENTE ALEJANDRO PINTOS ===\n\n";

try {
    $movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)
        ->orderBy('id', 'asc')
        ->get();
    
    echo "Total movimientos a corregir: " . $movimientos->count() . "\n\n";
    
    foreach ($movimientos as $mov) {
        $monto = (float)$mov->monto;
        
        // Calcular debe y haber según el tipo
        if ($mov->tipo === 'venta') {
            // Ventas: monto positivo = DEBE (aumenta deuda)
            $debe = abs($monto);
            $haber = 0;
        } else {
            // Pagos: monto positivo = HABER (reduce deuda)
            $debe = 0;
            $haber = abs($monto);
        }
        
        echo "ID {$mov->id}: {$mov->tipo} - Monto: {$monto} → Debe: {$debe}, Haber: {$haber}\n";
        
        $mov->debe = $debe;
        $mov->haber = $haber;
        $mov->save();
    }
    
    echo "\n✅ Movimientos actualizados correctamente\n\n";
    
    // Recalcular saldo
    $movimientos->load('fresh');
    $total_debe = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)->sum('debe');
    $total_haber = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)->sum('haber');
    $saldo_correcto = $total_haber - $total_debe;
    
    echo "RECÁLCULO:\n";
    echo "Total DEBE: $" . number_format($total_debe, 2) . "\n";
    echo "Total HABER: $" . number_format($total_haber, 2) . "\n";
    echo "Saldo correcto: $" . number_format($saldo_correcto, 2) . "\n\n";
    
    // Actualizar cliente
    $cliente = \App\Models\Cliente::find(2);
    $cliente->saldo_actual = $saldo_correcto;
    $cliente->save();
    
    echo "✅ Saldo del cliente actualizado a: $" . number_format($saldo_correcto, 2) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}