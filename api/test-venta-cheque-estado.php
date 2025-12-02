<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\MetodoPago;
use App\Services\Ventas\RegistrarVentaService;

echo "=== TEST: Verificar estado de venta con pago en Cheque ===\n\n";

try {
    // 1. Obtener cliente
    $cliente = Cliente::find(3);
    echo "Cliente: {$cliente->nombre}\n";
    echo "Límite crédito: \${$cliente->limite_credito}\n\n";
    
    // 2. Obtener un producto
    $producto = Producto::first();
    echo "Producto: {$producto->nombre} - Precio: \${$producto->precio}\n\n";
    
    // 3. Obtener método de pago Cheque
    $metodoCheque = MetodoPago::where('nombre', 'Cheque')->first();
    echo "Método pago: {$metodoCheque->nombre} (ID: {$metodoCheque->id})\n\n";
    
    // 4. Preparar datos de la venta
    $data = [
        'items' => [
            [
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'precio_unitario' => $producto->precio,
                'iva' => 0,
            ]
        ],
        'pagos' => [
            [
                'metodo_pago_id' => $metodoCheque->id,
                'monto' => $producto->precio,
                'fecha_pago' => now(),
                'numero_cheque' => 'TEST-' . rand(10000, 99999),
                'fecha_cheque' => now()->format('Y-m-d'),
                'fecha_cobro' => now()->addDays(15)->format('Y-m-d'),
                'observaciones_cheque' => 'Cheque de prueba para verificar estado',
            ]
        ],
        'fecha' => now(),
    ];
    
    // 5. Crear la venta
    $service = app(RegistrarVentaService::class);
    $venta = $service->ejecutar($cliente, $data);
    
    echo "✅ VENTA CREADA:\n";
    echo "   ID: {$venta->id}\n";
    echo "   Total: \${$venta->total}\n";
    echo "   Estado Pago: {$venta->estado_pago}\n\n";
    
    // 6. Verificar pagos
    echo "📋 PAGOS:\n";
    foreach ($venta->pagos as $pago) {
        echo "   - {$pago->metodoPago->nombre}: \${$pago->monto}\n";
    }
    echo "\n";
    
    // 7. Verificar cheque
    $cheque = $venta->cliente->cheques()->where('venta_id', $venta->id)->first();
    if ($cheque) {
        echo "📝 CHEQUE REGISTRADO:\n";
        echo "   ID: {$cheque->id}\n";
        echo "   Número: {$cheque->numero}\n";
        echo "   Estado: {$cheque->estado}\n";
        echo "   Fecha emisión: {$cheque->fecha_emision}\n";
        echo "   Fecha vencimiento: {$cheque->fecha_vencimiento}\n\n";
    }
    
    // 8. Validación
    if ($venta->estado_pago === 'pendiente') {
        echo "✅ CORRECTO: Venta con cheque quedó en estado 'pendiente'\n";
    } else {
        echo "❌ ERROR: Venta con cheque tiene estado '{$venta->estado_pago}' en lugar de 'pendiente'\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n";
}
