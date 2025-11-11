<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ACTUALIZAR CHEQUES A PENDIENTE ===\n";

// Actualizar todos los pagos con método "Cheque" que tienen estado_cheque NULL
$actualizados = \App\Models\Pago::whereHas('metodoPago', function($q) {
        $q->where('nombre', 'Cheque');
    })
    ->whereNull('estado_cheque')
    ->update(['estado_cheque' => 'pendiente']);

echo "Cheques actualizados a 'pendiente': {$actualizados}\n";

// Verificar
$cheques = \App\Models\Pago::with('metodoPago')
    ->whereHas('metodoPago', function($q) {
        $q->where('nombre', 'Cheque');
    })
    ->get();

echo "\n=== ESTADO ACTUAL DE CHEQUES ===\n";
foreach($cheques as $ch) {
    echo "ID:{$ch->id} | Venta:{$ch->venta_id} | Estado:{$ch->estado_cheque} | Monto:{$ch->monto}\n";
}
