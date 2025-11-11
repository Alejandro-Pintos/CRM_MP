<?php
require 'vendor/autoload.php';

// Inicializar la aplicación Laravel correctamente 
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CORRECCIÓN SALDO CLIENTE #2 ===\n";

try {
    $cliente = \App\Models\Cliente::find(2);
    echo "Cliente: {$cliente->nombre} {$cliente->apellido}\n";
    echo "Saldo actual en BD: {$cliente->saldo_actual}\n";
    
    // El cálculo correcto es: cliente debe $498.250
    $saldo_correcto = -498.25;
    
    echo "Saldo correcto: {$saldo_correcto}\n";
    
    // Actualizar el saldo
    $cliente->saldo_actual = $saldo_correcto;
    $cliente->save();
    
    echo "✅ Saldo actualizado correctamente\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}