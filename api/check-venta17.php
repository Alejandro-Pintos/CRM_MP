<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pago = \App\Models\Pago::where('venta_id', 17)->first();

if ($pago) {
    echo "=== PAGO VENTA #17 ===\n";
    echo "ID: {$pago->id}\n";
    echo "Estado cheque: " . ($pago->estado_cheque ?? 'NULL') . "\n";
    echo "Número cheque: " . ($pago->numero_cheque ?? 'NULL') . "\n";
    echo "Fecha cheque: " . ($pago->fecha_cheque ?? 'NULL') . "\n";
    echo "Fecha cobro: " . ($pago->fecha_cobro ?? 'NULL') . "\n";
} else {
    echo "No se encontró pago para venta #17\n";
}
