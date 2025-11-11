<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== LIMPIEZA DE DATOS DE PRUEBA ===\n\n";

try {
    \DB::beginTransaction();
    
    // IDs de clientes a limpiar
    $clienteIds = [2, 3];
    
    foreach ($clienteIds as $clienteId) {
        $cliente = \App\Models\Cliente::find($clienteId);
        if (!$cliente) {
            echo "Cliente #{$clienteId} no encontrado\n";
            continue;
        }
        
        echo "Limpiando datos del cliente: {$cliente->nombre} {$cliente->apellido} (ID: {$clienteId})\n";
        
        // 1. Eliminar movimientos de cuenta corriente
        $movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', $clienteId)->count();
        \App\Models\MovimientoCuentaCorriente::where('cliente_id', $clienteId)->delete();
        echo "  ✓ {$movimientos} movimientos de cuenta corriente eliminados\n";
        
        // 2. Eliminar detalles de venta
        $ventas = \App\Models\Venta::where('cliente_id', $clienteId)->get();
        foreach ($ventas as $venta) {
            \App\Models\DetalleVenta::where('venta_id', $venta->id)->delete();
        }
        echo "  ✓ Detalles de ventas eliminados\n";
        
        // 3. Eliminar pagos
        $pagos = \App\Models\Pago::whereHas('venta', function($q) use ($clienteId) {
            $q->where('cliente_id', $clienteId);
        })->count();
        \App\Models\Pago::whereHas('venta', function($q) use ($clienteId) {
            $q->where('cliente_id', $clienteId);
        })->delete();
        echo "  ✓ {$pagos} pagos eliminados\n";
        
        // 4. Eliminar ventas (con soft delete)
        $ventasCount = \App\Models\Venta::where('cliente_id', $clienteId)->count();
        \App\Models\Venta::where('cliente_id', $clienteId)->delete();
        echo "  ✓ {$ventasCount} ventas eliminadas (soft delete)\n";
        
        // 5. Eliminar pedidos
        $detallesPedido = \App\Models\DetallePedido::whereHas('pedido', function($q) use ($clienteId) {
            $q->where('cliente_id', $clienteId);
        })->count();
        \App\Models\DetallePedido::whereHas('pedido', function($q) use ($clienteId) {
            $q->where('cliente_id', $clienteId);
        })->delete();
        
        $pedidos = \App\Models\Pedido::where('cliente_id', $clienteId)->count();
        \App\Models\Pedido::where('cliente_id', $clienteId)->delete();
        echo "  ✓ {$pedidos} pedidos y {$detallesPedido} detalles eliminados\n";
        
        // 6. Resetear saldo del cliente a 0
        $cliente->saldo_actual = 0;
        $cliente->save();
        echo "  ✓ Saldo actual reseteado a $0\n";
        
        echo "\n";
    }
    
    \DB::commit();
    
    echo "✅ LIMPIEZA COMPLETADA EXITOSAMENTE\n\n";
    
    echo "Estado final de los clientes:\n";
    foreach ($clienteIds as $clienteId) {
        $cliente = \App\Models\Cliente::find($clienteId);
        if ($cliente) {
            echo "- {$cliente->nombre} {$cliente->apellido}: Saldo = \${$cliente->saldo_actual}, Límite = \${$cliente->limite_credito}\n";
        }
    }
    
} catch (Exception $e) {
    \DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}