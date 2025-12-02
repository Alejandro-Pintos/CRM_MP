<?php

require __DIR__ . '/api/vendor/autoload.php';
$app = require_once __DIR__ . '/api/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cliente;
use App\Models\MovimientoCuentaCorriente;

$cliente = Cliente::find(3);

echo "\n";
echo "Cliente: {$cliente->nombre} {$cliente->apellido}\n";
echo "Saldo actual: {$cliente->saldo_actual}\n";
echo "\n";
echo "MOVIMIENTOS:\n";
echo "──────────────────────────────────────────────────────────────────────────────\n";

foreach ($cliente->movimientosCuentaCorriente()->orderBy('fecha')->orderBy('id')->get() as $m) {
    echo sprintf(
        "ID:%-3d | %s | %-5s | Venta:%-4s | DEBE:%-10s | HABER:%-10s | MONTO:%-10s | %s\n",
        $m->id,
        $m->fecha->format('Y-m-d H:i'),
        $m->tipo,
        $m->venta_id ?? '-',
        number_format($m->debe, 0),
        number_format($m->haber, 0),
        number_format($m->monto, 0),
        substr($m->descripcion ?? '-', 0, 30)
    );
}

echo "──────────────────────────────────────────────────────────────────────────────\n";
echo "\n";

$debe = $cliente->movimientosCuentaCorriente()->where('tipo', 'venta')->sum('debe');
$haber = $cliente->movimientosCuentaCorriente()->where('tipo', 'pago')->sum('haber');

echo "Total DEBE (ventas):  " . number_format($debe, 2) . "\n";
echo "Total HABER (pagos):  " . number_format($haber, 2) . "\n";
echo "Saldo (DEBE-HABER):   " . number_format($debe - $haber, 2) . "\n";
echo "\n";

if (($debe - $haber) < 0) {
    echo "❌ PROBLEMA DETECTADO: Saldo negativo\n";
    echo "   Esto significa que hay más PAGOS que VENTAS registrados.\n";
    echo "   Posibles causas:\n";
    echo "   - Pagos registrados sin venta asociada\n";
    echo "   - Movimientos tipo 'pago' con HABER cuando debería ser DEBE\n";
    echo "   - Datos de prueba incorrectos\n";
}
