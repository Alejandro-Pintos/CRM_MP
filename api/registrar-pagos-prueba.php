<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PagoProveedor;
use App\Models\MetodoPago;
use App\Services\ProveedorEstadoCuentaService;

echo "💰 Registrando pagos de prueba...\n\n";

$proveedorId = 2;

// Buscar método de pago "Transferencia" o crear uno
$metodoTransferencia = MetodoPago::where('nombre', 'Transferencia')->first();
if (!$metodoTransferencia) {
    echo "⚠️  Método de pago 'Transferencia' no encontrado, usando NULL\n";
    $metodoId = null;
} else {
    $metodoId = $metodoTransferencia->id;
    echo "✅ Método de pago: {$metodoTransferencia->nombre} (ID: {$metodoId})\n\n";
}

// Pago 1: Pago parcial
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Registrando Pago 1 - Pago parcial de factura\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$pago1 = PagoProveedor::create([
    'proveedor_id' => $proveedorId,
    'fecha_pago' => now()->subDays(3),
    'monto' => 100000.00,
    'metodo_pago_id' => $metodoId,
    'referencia' => 'FC-001-0001234',
    'concepto' => 'Pago parcial factura',
    'observaciones' => 'Pago a cuenta de factura FC-001-0001234'
]);

echo "✅ Pago 1 registrado:\n";
echo "   ID: {$pago1->id}\n";
echo "   Fecha: {$pago1->fecha_pago->format('d/m/Y')}\n";
echo "   Monto: $" . number_format($pago1->monto, 2, ',', '.') . "\n";
echo "   Concepto: {$pago1->concepto}\n\n";

// Verificar estado actual
$service = new ProveedorEstadoCuentaService();
$resumen = $service->getResumen($proveedorId);

echo "📊 ESTADO ACTUAL:\n";
echo "   Total Compras:  $" . number_format($resumen['total_compras'], 2, ',', '.') . "\n";
echo "   Total Pagos:    $" . number_format($resumen['total_pagos'], 2, ',', '.') . "\n";
echo "   Saldo:          $" . number_format($resumen['saldo'], 2, ',', '.') . "\n";
echo "   Estado:         {$resumen['estado']} - {$resumen['estado_texto']}\n\n";

// Pago 2: Otro pago parcial
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Registrando Pago 2 - Anticipo\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$pago2 = PagoProveedor::create([
    'proveedor_id' => $proveedorId,
    'fecha_pago' => now()->subDay(),
    'monto' => 150000.00,
    'metodo_pago_id' => $metodoId,
    'referencia' => 'ANT-001',
    'concepto' => 'Anticipo',
    'observaciones' => 'Anticipo para próximas compras'
]);

echo "✅ Pago 2 registrado:\n";
echo "   ID: {$pago2->id}\n";
echo "   Fecha: {$pago2->fecha_pago->format('d/m/Y')}\n";
echo "   Monto: $" . number_format($pago2->monto, 2, ',', '.') . "\n";
echo "   Concepto: {$pago2->concepto}\n\n";

// Verificar estado final
$resumen = $service->getResumen($proveedorId);

echo "📊 ESTADO FINAL:\n";
echo "   Total Compras:  $" . number_format($resumen['total_compras'], 2, ',', '.') . "\n";
echo "   Total Pagos:    $" . number_format($resumen['total_pagos'], 2, ',', '.') . "\n";
echo "   Saldo:          $" . number_format($resumen['saldo'], 2, ',', '.') . "\n";
echo "   Estado:         {$resumen['estado']} - {$resumen['estado_texto']}\n\n";

// Mostrar movimientos completos
$movimientos = $service->getMovimientos($proveedorId);

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📋 MOVIMIENTOS COMPLETOS:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
printf("%-12s | %-10s | %15s | %15s | %15s\n", 
    "FECHA", "TIPO", "DÉBITO", "CRÉDITO", "SALDO");
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

foreach ($movimientos as $mov) {
    $fecha = date('d/m/Y', strtotime($mov['fecha']));
    $tipo = $mov['tipo'];
    $debito = $mov['debito'] > 0 ? '$' . number_format($mov['debito'], 2, ',', '.') : '-';
    $credito = $mov['credito'] > 0 ? '$' . number_format($mov['credito'], 2, ',', '.') : '-';
    $saldo = '$' . number_format($mov['saldo_acumulado'], 2, ',', '.');
    
    printf("%-12s | %-10s | %15s | %15s | %15s\n",
        $fecha, $tipo, 
        str_pad($debito, 15, ' ', STR_PAD_LEFT),
        str_pad($credito, 15, ' ', STR_PAD_LEFT),
        str_pad($saldo, 15, ' ', STR_PAD_LEFT)
    );
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "✅ Pagos registrados exitosamente!\n\n";

echo "🎯 Próximo paso:\n";
echo "   1. Abrir navegador en http://localhost:8080/proveedores\n";
echo "   2. Deberías ver el proveedor 'Aserradero El Pino S.A.'\n";
echo "   3. Badge debería mostrar: '🟢 A favor: \$34.350,00'\n";
echo "   4. Click en estado de cuenta para ver los movimientos\n\n";
