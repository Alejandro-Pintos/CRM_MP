<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Cliente;
use App\Models\Producto;

echo "=== TEST CREAR PEDIDO COMPLETO ===\n\n";

// Verificar datos disponibles
$cliente = Cliente::first();
$producto = Producto::first();

if (!$cliente) {
    echo "❌ No hay clientes en la BD\n";
    exit(1);
}

if (!$producto) {
    echo "❌ No hay productos en la BD\n";
    exit(1);
}

echo "✅ Cliente encontrado: {$cliente->nombre} {$cliente->apellido} (ID: {$cliente->id})\n";
echo "✅ Producto encontrado: {$producto->nombre} (ID: {$producto->id})\n\n";

// Simular datos del frontend
$datosDelFrontend = [
    'cliente_id' => $cliente->id,
    'fecha_pedido' => now()->format('Y-m-d'),
    'fecha_entrega_aprox' => now()->addDays(7)->format('Y-m-d'),
    'fecha_despacho' => now()->addDays(2)->format('Y-m-d'),
    'estado' => 'pendiente',
    'direccion_entrega' => 'Dirección Test',
    'ciudad_entrega' => 'Ciudad Test',
    'observaciones' => 'Test desde script',
    'items' => [
        [
            'producto_id' => $producto->id,
            'cantidad' => 5,
            'precio_compra' => 100,
            'precio_venta' => 200,
            'porcentaje_iva' => 21,
            'porcentaje_extra' => 10,
            'precio_unitario' => 266.20,
            'observaciones' => null,
        ]
    ]
];

echo "Datos a enviar:\n";
print_r($datosDelFrontend);
echo "\n";

try {
    // Simular lo que hace el controlador
    $data = $datosDelFrontend;
    $items = $data['items'];
    unset($data['items']);
    
    $pedido = Pedido::create($data);
    echo "✅ Pedido creado: ID #{$pedido->id}\n";
    
    foreach ($items as $item) {
        $detalle = DetallePedido::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $item['producto_id'],
            'cantidad' => $item['cantidad'],
            'precio_compra' => $item['precio_compra'] ?? 0,
            'precio_venta' => $item['precio_venta'] ?? 0,
            'porcentaje_iva' => $item['porcentaje_iva'] ?? 0,
            'porcentaje_extra' => $item['porcentaje_extra'] ?? 0,
            'precio_unitario' => $item['precio_unitario'],
            'observaciones' => $item['observaciones'] ?? null,
        ]);
        echo "✅ Item creado: Producto ID {$detalle->producto_id}, Cantidad {$detalle->cantidad}\n";
    }
    
    echo "\n✅ PEDIDO CREADO EXITOSAMENTE\n";
    echo "Total pedidos en BD: " . Pedido::count() . "\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
