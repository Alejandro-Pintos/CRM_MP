<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Pedido;
use App\Models\DetallePedido;

echo "Creando pedido de prueba...\n";

$pedido = Pedido::create([
    'cliente_id' => 1,
    'fecha_pedido' => now(),
    'fecha_entrega_aprox' => now()->addDays(7),
    'fecha_despacho' => now()->addDays(2),
    'estado' => 'pendiente',
    'direccion_entrega' => 'Calle Test 123',
    'ciudad_entrega' => 'Ciudad Test',
    'observaciones' => 'Pedido de prueba manual',
]);

echo "Pedido #{$pedido->id} creado\n";

$detalle = DetallePedido::create([
    'pedido_id' => $pedido->id,
    'producto_id' => 1,
    'cantidad' => 5,
    'precio_compra' => 100,
    'precio_venta' => 150,
    'porcentaje_iva' => 21,
    'precio_unitario' => 150,
]);

echo "Detalle creado para producto ID {$detalle->producto_id}\n";
echo "Total pedidos en BD: " . Pedido::count() . "\n";
