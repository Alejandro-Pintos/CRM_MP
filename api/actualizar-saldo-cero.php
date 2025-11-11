<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cliente = \App\Models\Cliente::find(2);
$cliente->saldo_actual = 0;
$cliente->save();

echo "✅ Saldo actualizado a 0\n";
