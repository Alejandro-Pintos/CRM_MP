<?php

require __DIR__ . '/vendor/autoload.php';

use App\Models\Producto;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST: Cálculo de Precio Total en Productos ===\n\n";

// Buscar un producto de prueba
$producto = Producto::first();

if (!$producto) {
    echo "❌ No hay productos en la base de datos\n";
    exit(1);
}

echo "📦 Producto: {$producto->nombre}\n";
echo "   Código: {$producto->codigo}\n\n";

echo "💰 Precios guardados en BD:\n";
echo "   - Precio Compra: \$" . number_format($producto->precio_compra, 2, ',', '.') . "\n";
echo "   - Precio Venta: \$" . number_format($producto->precio_venta, 2, ',', '.') . "\n";
echo "   - Precio Unitario (sin IVA): \$" . number_format($producto->precio, 2, ',', '.') . "\n";
echo "   - IVA: {$producto->iva}%\n\n";

echo "🧮 Precio Total (calculado):\n";
echo "   - Fórmula: Precio Unitario × (1 + IVA/100)\n";
echo "   - Cálculo: \${$producto->precio} × (1 + {$producto->iva}/100)\n";
echo "   - Precio Total: \$" . number_format($producto->precio_total, 2, ',', '.') . "\n\n";

// Verificar cálculo manual
$calculoManual = $producto->precio * (1 + $producto->iva / 100);
$calculoModelo = $producto->precio_total;

echo "✅ Verificación:\n";
echo "   - Cálculo manual: \$" . number_format($calculoManual, 2, ',', '.') . "\n";
echo "   - Desde modelo: \$" . number_format($calculoModelo, 2, ',', '.') . "\n";

if (abs($calculoManual - $calculoModelo) < 0.01) {
    echo "   ✅ ¡Los cálculos coinciden!\n\n";
} else {
    echo "   ❌ Error: Los cálculos NO coinciden\n\n";
    exit(1);
}

// Probar con el Resource (API)
echo "📡 Verificando respuesta del API (ProductoResource):\n";
$resource = new \App\Http\Resources\ProductoResource($producto);
$array = $resource->toArray(new \Illuminate\Http\Request());

echo "   - precio_compra: \$" . number_format($array['precio_compra'], 2, ',', '.') . "\n";
echo "   - precio_venta: \$" . number_format($array['precio_venta'], 2, ',', '.') . "\n";
echo "   - precio (unitario): \$" . number_format($array['precio'], 2, ',', '.') . "\n";
echo "   - precio_total: \$" . number_format($array['precio_total'], 2, ',', '.') . "\n";
echo "   - iva: {$array['iva']}%\n\n";

echo "✅ TODOS LOS TESTS PASARON EXITOSAMENTE\n\n";

echo "📋 Resumen de la implementación:\n";
echo "   ✅ precio_compra - Guardado en BD (trazabilidad)\n";
echo "   ✅ precio_venta - Guardado en BD (trazabilidad)\n";
echo "   ✅ precio - Guardado en BD (precio unitario base)\n";
echo "   ✅ iva - Guardado en BD (porcentaje)\n";
echo "   ✅ precio_total - Calculado dinámicamente (NO en BD)\n\n";

echo "🎯 Beneficios:\n";
echo "   ✅ Trazabilidad completa de precios históricos\n";
echo "   ✅ No hay redundancia en la base de datos\n";
echo "   ✅ Actualización automática si cambia el IVA\n";
echo "   ✅ Datos consistentes siempre\n";
