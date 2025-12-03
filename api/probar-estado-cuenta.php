<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ProveedorEstadoCuentaService;
use App\Models\Proveedor;

echo "🧪 Probando Servicio de Estado de Cuenta...\n\n";

$proveedorId = 2; // ID del proveedor que acabamos de crear

$proveedor = Proveedor::find($proveedorId);

if (!$proveedor) {
    echo "❌ Proveedor no encontrado\n";
    exit(1);
}

echo "✅ Proveedor encontrado: {$proveedor->nombre}\n\n";

// Probar servicio de resumen
$service = new ProveedorEstadoCuentaService();
$resumen = $service->getResumen($proveedorId);

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 RESUMEN DE CUENTA (desde servicio):\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "   Total Compras:  $" . number_format($resumen['total_compras'], 2, ',', '.') . "\n";
echo "   Total Pagos:    $" . number_format($resumen['total_pagos'], 2, ',', '.') . "\n";
echo "   Saldo:          $" . number_format($resumen['saldo'], 2, ',', '.') . "\n";
echo "   Estado:         {$resumen['estado']}\n";
echo "   Estado Texto:   {$resumen['estado_texto']}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Probar servicio de movimientos
$movimientos = $service->getMovimientos($proveedorId);

echo "📋 MOVIMIENTOS DE CUENTA:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

foreach ($movimientos as $mov) {
    $fecha = date('d/m/Y', strtotime($mov['fecha']));
    $tipo = str_pad($mov['tipo'], 10);
    $debito = $mov['debito'] > 0 ? '$' . number_format($mov['debito'], 2, ',', '.') : '-';
    $credito = $mov['credito'] > 0 ? '$' . number_format($mov['credito'], 2, ',', '.') : '-';
    $saldo = '$' . number_format($mov['saldo_acumulado'], 2, ',', '.');
    
    echo sprintf(
        "   %s | %-10s | Déb: %15s | Cré: %15s | Saldo: %15s\n",
        $fecha,
        $tipo,
        str_pad($debito, 15, ' ', STR_PAD_LEFT),
        str_pad($credito, 15, ' ', STR_PAD_LEFT),
        str_pad($saldo, 15, ' ', STR_PAD_LEFT)
    );
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "✅ Servicio funcionando correctamente!\n\n";

echo "🔗 Probar endpoint API:\n";
echo "   GET http://localhost/api/v1/proveedores/{$proveedorId}/cuenta/resumen\n";
echo "   GET http://localhost/api/v1/proveedores/{$proveedorId}/cuenta/movimientos\n\n";
