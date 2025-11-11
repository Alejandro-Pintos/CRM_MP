<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Pedido;
use App\Models\DetallePedido;

echo "=== PRUEBA SISTEMA DE PRECIOS ===\n\n";

// Datos de prueba
$precioCompra = 100;
$precioVenta = 200;
$porcentajeIva = 21;
$porcentajeExtra = 10; // 10% extra por venta minorista
$cantidad = 5;

echo "Datos base:\n";
echo "- P. Compra: $" . $precioCompra . "\n";
echo "- P. Venta (mayorista): $" . $precioVenta . "\n";
echo "- IVA: " . $porcentajeIva . "%\n";
echo "- Extra (minorista): " . $porcentajeExtra . "%\n";
echo "- Cantidad: " . $cantidad . "\n\n";

// Cálculo manual
$precioConIva = $precioVenta * (1 + $porcentajeIva / 100);
$precioUnitario = $precioConIva * (1 + $porcentajeExtra / 100);
$subtotal = $precioUnitario * $cantidad;

echo "Cálculos:\n";
echo "- P. Venta + IVA: $" . number_format($precioConIva, 2) . " ($precioVenta * 1.21)\n";
echo "- Precio Unitario final: $" . number_format($precioUnitario, 2) . " ($precioConIva * 1.10)\n";
echo "- Subtotal (x$cantidad): $" . number_format($subtotal, 2) . "\n\n";

echo "Creando pedido en BD...\n";

$pedido = Pedido::create([
    'cliente_id' => 1,
    'fecha_pedido' => now(),
    'fecha_entrega_aprox' => now()->addDays(7),
    'fecha_despacho' => now()->addDays(2),
    'estado' => 'pendiente',
    'direccion_entrega' => 'Calle Test 456',
    'ciudad_entrega' => 'Ciudad Test',
    'observaciones' => 'Prueba sistema de precios',
]);

$detalle = DetallePedido::create([
    'pedido_id' => $pedido->id,
    'producto_id' => 1,
    'cantidad' => $cantidad,
    'precio_compra' => $precioCompra,
    'precio_venta' => $precioVenta,
    'porcentaje_iva' => $porcentajeIva,
    'porcentaje_extra' => $porcentajeExtra,
    'precio_unitario' => $precioUnitario,
]);

echo "✅ Pedido #{$pedido->id} creado correctamente\n";
echo "✅ Detalle con porcentaje_extra guardado: {$detalle->porcentaje_extra}%\n";
echo "\nTotal pedidos en BD: " . Pedido::count() . "\n";
