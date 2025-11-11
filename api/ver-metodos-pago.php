<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== MÉTODOS DE PAGO ===\n";
$metodos = \App\Models\MetodoPago::all();
foreach($metodos as $m) {
    echo "ID:{$m->id} | Nombre: '{$m->nombre}'\n";
}
