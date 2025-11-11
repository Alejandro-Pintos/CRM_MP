<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CREANDO VENTA DE PRUEBA CON CUENTA CORRIENTE ===\n\n";

try {
    DB::beginTransaction();
    
    // Obtener cliente Alejandro
    $cliente = \App\Models\Cliente::find(2);
    echo "Cliente: {$cliente->nombre} {$cliente->apellido}\n";
    echo "Saldo actual antes: $" . number_format($cliente->saldo_actual, 2) . "\n";
    echo "Límite crédito: $" . number_format($cliente->limite_credito, 2) . "\n\n";
    
    // Obtener algunos productos
    $productos = \App\Models\Producto::take(3)->get();
    
    if ($productos->count() < 3) {
        echo "❌ No hay suficientes productos en la base de datos\n";
        exit;
    }
    
    echo "Productos a vender:\n";
    foreach ($productos as $p) {
        echo "  - {$p->nombre}: $" . number_format($p->precio, 2) . "\n";
    }
    echo "\n";
    
    // Preparar datos de venta
    $ventaData = [
        'cliente_id' => 2,
        'fecha' => now(),
        'items' => [
            [
                'producto_id' => $productos[0]->id,
                'cantidad' => 2,
                'precio_unitario' => $productos[0]->precio,
                'iva' => 21,
            ],
            [
                'producto_id' => $productos[1]->id,
                'cantidad' => 1,
                'precio_unitario' => $productos[1]->precio,
                'iva' => 21,
            ],
            [
                'producto_id' => $productos[2]->id,
                'cantidad' => 3,
                'precio_unitario' => $productos[2]->precio,
                'iva' => 21,
            ],
        ],
        'pagos' => [], // Sin pagos = todo va a cuenta corriente
    ];
    
    // Crear venta usando el servicio
    $ventaService = new \App\Services\VentaService();
    $venta = $ventaService->crearVenta($ventaData, 1);
    
    echo "✅ Venta creada: ID #{$venta->id}\n";
    echo "   Total: $" . number_format($venta->total, 2) . "\n";
    echo "   Estado: {$venta->estado_pago}\n\n";
    
    // Verificar movimiento creado
    $movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)
        ->where('referencia_id', $venta->id)
        ->get();
    
    echo "Movimientos de CC creados: {$movimientos->count()}\n";
    foreach ($movimientos as $mov) {
        echo "  - ID {$mov->id}: {$mov->tipo} - Debe: $" . number_format($mov->debe, 2) . 
             ", Haber: $" . number_format($mov->haber, 2) . "\n";
        echo "    Descripción: {$mov->descripcion}\n";
    }
    echo "\n";
    
    // Verificar saldo actualizado
    $cliente->refresh();
    echo "Saldo actual después: $" . number_format($cliente->saldo_actual, 2) . "\n";
    echo "Crédito disponible: $" . number_format($cliente->limite_credito - abs($cliente->saldo_actual), 2) . "\n\n";
    
    DB::commit();
    
    echo "✅ VENTA CREADA EXITOSAMENTE\n";
    echo "Venta ID: {$venta->id}\n";
    echo "Ahora recarga el navegador y verifica la cuenta corriente de Alejandro Pintos\n";
    
} catch (Exception $e) {
    DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}