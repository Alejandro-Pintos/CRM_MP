<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== VERIFICACIÓN ALEJANDRO PINTOS (ID: 2) ===\n\n";

try {
    $cliente = \App\Models\Cliente::find(2);
    echo "Cliente: {$cliente->nombre} {$cliente->apellido}\n";
    echo "Saldo actual en BD: {$cliente->saldo_actual}\n";
    echo "Límite de crédito: {$cliente->limite_credito}\n\n";
    
    // Obtener todos los movimientos
    $movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)
        ->orderBy('fecha', 'asc')
        ->orderBy('id', 'asc')
        ->get();
    
    echo "MOVIMIENTOS DE CUENTA CORRIENTE:\n";
    echo str_repeat("-", 100) . "\n";
    printf("%-5s %-20s %-10s %-40s %15s %15s\n", "ID", "FECHA", "TIPO", "DESCRIPCIÓN", "DEBE", "HABER");
    echo str_repeat("-", 100) . "\n";
    
    $total_debe = 0;
    $total_haber = 0;
    
    foreach ($movimientos as $mov) {
        printf("%-5s %-20s %-10s %-40s %15.2f %15.2f\n", 
            $mov->id,
            $mov->fecha,
            $mov->tipo,
            substr($mov->descripcion, 0, 40),
            $mov->debe,
            $mov->haber
        );
        $total_debe += $mov->debe;
        $total_haber += $mov->haber;
    }
    
    echo str_repeat("-", 100) . "\n";
    printf("%67s %15.2f %15.2f\n", "TOTALES:", $total_debe, $total_haber);
    echo str_repeat("-", 100) . "\n\n";
    
    $saldo_calculado = $total_haber - $total_debe;
    
    echo "RESUMEN:\n";
    echo "Total DEBE (ventas): $" . number_format($total_debe, 2) . "\n";
    echo "Total HABER (pagos): $" . number_format($total_haber, 2) . "\n";
    echo "Saldo calculado (HABER - DEBE): $" . number_format($saldo_calculado, 2) . "\n";
    echo "Saldo en BD: $" . number_format($cliente->saldo_actual, 2) . "\n";
    echo "Diferencia: $" . number_format($cliente->saldo_actual - $saldo_calculado, 2) . "\n\n";
    
    if ($cliente->saldo_actual != $saldo_calculado) {
        echo "⚠️  EL SALDO NECESITA SER RECALCULADO\n";
        echo "¿Actualizar saldo? (El script solo muestra información)\n";
    } else {
        echo "✅ El saldo está correcto\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}