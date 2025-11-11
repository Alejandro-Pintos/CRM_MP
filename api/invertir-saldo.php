<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== INVERTIR SALDO (DEUDA NEGATIVA) ===\n";

// El saldo actual es +998.250 pero debería ser -998.250
// porque representa deuda del cliente

$cliente = \App\Models\Cliente::find(2);
echo "Saldo actual: {$cliente->saldo_actual}\n";

// Invertir el signo
$cliente->saldo_actual = -998250;
$cliente->save();

echo "✅ Saldo corregido: {$cliente->saldo_actual}\n";
echo "Crédito disponible: " . (1000000 + $cliente->saldo_actual) . "\n";
