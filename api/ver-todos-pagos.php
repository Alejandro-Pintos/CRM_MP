<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TODOS LOS PAGOS ===\n\n";

$pagos = DB::table('pagos')
    ->join('ventas', 'pagos.venta_id', '=', 'ventas.id')
    ->join('clientes', 'ventas.cliente_id', '=', 'clientes.id')
    ->join('metodos_pago', 'pagos.metodo_pago_id', '=', 'metodos_pago.id')
    ->select(
        'pagos.id',
        'pagos.venta_id',
        'pagos.metodo_pago_id',
        'metodos_pago.nombre as metodo_nombre',
        'pagos.monto',
        'pagos.estado_cheque',
        'pagos.numero_cheque',
        'pagos.fecha_cheque',
        'pagos.fecha_cobro',
        'pagos.observaciones_cheque',
        'clientes.nombre as cliente_nombre',
        'clientes.apellido as cliente_apellido'
    )
    ->get();

foreach ($pagos as $pago) {
    echo "=== Pago ID: {$pago->id} ===\n";
    echo "Venta: #{$pago->venta_id}\n";
    echo "Cliente: {$pago->cliente_nombre} {$pago->cliente_apellido}\n";
    echo "Método de Pago: {$pago->metodo_nombre} (ID: {$pago->metodo_pago_id})\n";
    echo "Monto: $" . number_format($pago->monto, 2) . "\n";
    echo "Estado Cheque: " . ($pago->estado_cheque ?? 'NULL') . "\n";
    echo "Número Cheque: " . ($pago->numero_cheque ?? 'NULL') . "\n";
    echo "Fecha Cheque: " . ($pago->fecha_cheque ?? 'NULL') . "\n";
    echo "Fecha Cobro: " . ($pago->fecha_cobro ?? 'NULL') . "\n";
    echo "Observaciones: " . ($pago->observaciones_cheque ?? 'NULL') . "\n";
    echo "\n";
}

echo "\n=== RESUMEN ===\n";
echo "Total de pagos: " . $pagos->count() . "\n";
echo "Con estado_cheque 'pendiente': " . $pagos->where('estado_cheque', 'pendiente')->count() . "\n";
echo "Con estado_cheque NULL: " . $pagos->whereNull('estado_cheque')->count() . "\n";
