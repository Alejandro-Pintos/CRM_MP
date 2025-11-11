<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DIAGNÓSTICO DE PEDIDOS ===\n\n";

$pedidos = \App\Models\Pedido::with(['cliente', 'items.producto'])->get();

echo "Total pedidos: " . $pedidos->count() . "\n\n";

if ($pedidos->count() === 0) {
    echo "No hay pedidos registrados\n";
} else {
    foreach ($pedidos as $pedido) {
        echo "Pedido ID: {$pedido->id}\n";
        echo "Cliente ID: {$pedido->cliente_id}\n";
        if ($pedido->cliente) {
            echo "Cliente: {$pedido->cliente->nombre} {$pedido->cliente->apellido}\n";
        } else {
            echo "Cliente: NO ENCONTRADO\n";
        }
        echo "Fecha: {$pedido->fecha_pedido}\n";
        echo "Estado: {$pedido->estado}\n";
        echo "Items: " . $pedido->items->count() . "\n";
        echo "\n";
    }
}

echo "\n=== CLIENTES DISPONIBLES ===\n";
$clientes = \App\Models\Cliente::all();
echo "Total clientes: " . $clientes->count() . "\n";
foreach ($clientes as $cliente) {
    echo "{$cliente->id} - {$cliente->nombre} {$cliente->apellido}\n";
}

echo "\n=== FIN ===\n";
