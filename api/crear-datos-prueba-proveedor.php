<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Proveedor;
use App\Models\Compra;
use App\Models\PagoProveedor;

echo "🚀 Creando datos de prueba para Proveedor...\n\n";

// Crear proveedor
$proveedor = Proveedor::create([
    'nombre' => 'Aserradero El Pino S.A.',
    'razon_social' => 'Aserradero El Pino Sociedad Anónima',
    'cuit' => '30-71234567-8',
    'email' => 'ventas@elpino.com.ar',
    'telefono' => '3764-123456',
    'direccion' => 'Ruta 14 Km 850',
    'ciudad' => 'Montecarlo',
    'provincia' => 'Misiones',
    'codigo_postal' => '3384',
    'contacto_nombre' => 'Juan Pérez',
    'contacto_telefono' => '3764-654321',
    'condicion_pago' => 'cuenta_corriente',
    'limite_credito' => 500000.00,
    'observaciones' => 'Proveedor principal de maderas',
    'estado' => 'activo'
]);

echo "✅ Proveedor creado:\n";
echo "   ID: {$proveedor->id}\n";
echo "   Nombre: {$proveedor->nombre}\n";
echo "   CUIT: {$proveedor->cuit}\n\n";

// Crear primera compra
$compra1 = Compra::create([
    'proveedor_id' => $proveedor->id,
    'fecha_compra' => now()->subDays(15),
    'numero_factura' => 'FC-001-0001234',
    'subtotal' => 150000.00,
    'impuestos' => 31500.00,
    'monto_total' => 181500.00,
    'estado' => 'pendiente',
    'observaciones' => 'Compra de tablas de pino tratado'
]);

echo "✅ Compra 1 creada:\n";
echo "   ID: {$compra1->id}\n";
echo "   Factura: {$compra1->numero_factura}\n";
echo "   Monto: $" . number_format($compra1->monto_total, 2, ',', '.') . "\n";
echo "   Fecha: {$compra1->fecha_compra->format('d/m/Y')}\n\n";

// Crear segunda compra
$compra2 = Compra::create([
    'proveedor_id' => $proveedor->id,
    'fecha_compra' => now()->subDays(7),
    'numero_factura' => 'FC-001-0001235',
    'subtotal' => 85000.00,
    'impuestos' => 17850.00,
    'monto_total' => 102850.00,
    'estado' => 'pendiente',
    'observaciones' => 'Compra de tirantes y listones'
]);

echo "✅ Compra 2 creada:\n";
echo "   ID: {$compra2->id}\n";
echo "   Factura: {$compra2->numero_factura}\n";
echo "   Monto: $" . number_format($compra2->monto_total, 2, ',', '.') . "\n";
echo "   Fecha: {$compra2->fecha_compra->format('d/m/Y')}\n\n";

// Calcular totales
$totalCompras = $compra1->monto_total + $compra2->monto_total;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 RESUMEN INICIAL (SIN PAGOS):\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "   Total Compras: $" . number_format($totalCompras, 2, ',', '.') . "\n";
echo "   Total Pagos:   $0,00\n";
echo "   Saldo (Deuda): $" . number_format($totalCompras, 2, ',', '.') . "\n";
echo "   Estado:        🔴 DEUDA\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "🎯 Ahora puedes:\n";
echo "   1. Ir a /proveedores en el admin\n";
echo "   2. Ver el badge rojo con la deuda\n";
echo "   3. Abrir el estado de cuenta\n";
echo "   4. Registrar un pago\n\n";

echo "💡 ID del proveedor: {$proveedor->id}\n";
echo "💡 Para registrar un pago vía API:\n";
echo "   POST /api/v1/proveedores/{$proveedor->id}/pagos\n";
echo "   Body: {\"fecha_pago\": \"" . now()->format('Y-m-d') . "\", \"monto\": 100000, \"concepto\": \"pago_factura\"}\n\n";
