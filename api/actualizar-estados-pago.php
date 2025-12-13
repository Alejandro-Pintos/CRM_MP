<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Venta;
use Illuminate\Support\Facades\DB;

echo "Actualizando estados de pago de ventas...\n\n";

$ventas = Venta::with(['pagos', 'cheques'])->get();
$actualizadas = 0;

foreach ($ventas as $venta) {
    $estadoAnterior = DB::table('ventas')->where('id', $venta->id)->value('estado_pago');
    $estadoCalculado = $venta->estado_pago; // Trigger del Accessor
    
    if ($estadoAnterior !== $estadoCalculado) {
        DB::table('ventas')->where('id', $venta->id)->update(['estado_pago' => $estadoCalculado]);
        echo "✓ Venta #{$venta->id}: {$estadoAnterior} → {$estadoCalculado}\n";
        $actualizadas++;
    } else {
        echo "  Venta #{$venta->id}: {$estadoCalculado} (sin cambios)\n";
    }
}

echo "\n✅ Completado: {$actualizadas} ventas actualizadas de {$ventas->count()} totales\n";
