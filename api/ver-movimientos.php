<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TODOS LOS MOVIMIENTOS CLIENTE #2 ===\n";

$movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)
    ->orderBy('id', 'asc')
    ->get();

foreach ($movimientos as $mov) {
    echo sprintf(
        "ID:%d | Tipo:%s | Debe:%s | Haber:%s | Monto:%s | Desc:%s\n",
        $mov->id,
        $mov->tipo,
        $mov->debe ?? 'NULL',
        $mov->haber ?? 'NULL', 
        $mov->monto ?? 'NULL',
        $mov->descripcion
    );
}

echo "\nTOTALES:\n";
echo "Total DEBE: " . $movimientos->sum('debe') . "\n";
echo "Total HABER: " . $movimientos->sum('haber') . "\n";
echo "Saldo (haber-debe): " . ($movimientos->sum('haber') - $movimientos->sum('debe')) . "\n";
