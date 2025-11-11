<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$total_debe = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)->sum('debe');
$total_haber = \App\Models\MovimientoCuentaCorriente::where('cliente_id', 2)->sum('haber');
$saldo_correcto = $total_haber - $total_debe;

echo "Saldo correcto: $" . number_format($saldo_correcto, 2) . "\n";

$cliente = \App\Models\Cliente::find(2);
$cliente->saldo_actual = $saldo_correcto;
$cliente->save();

echo "✅ Saldo actualizado\n";