<?php
// Script para recalcular saldo basándose en movimientos reales

// Simulación de los movimientos que se ven en el frontend
echo "=== RECÁLCULO MANUAL BASADO EN FRONTEND ===\n";

// Movimientos visibles en el frontend:
$movimientos = [
    ['tipo' => 'venta', 'descripcion' => 'Saldo pendiente venta #4', 'debe' => 998250, 'haber' => 0],
    ['tipo' => 'pago', 'descripcion' => 'Pago venta #4', 'debe' => 0, 'haber' => 998250],
    ['tipo' => 'venta', 'descripcion' => 'Saldo pendiente venta #5', 'debe' => 186000, 'haber' => 0],
    ['tipo' => 'pago', 'descripcion' => 'Pago venta #5', 'debe' => 0, 'haber' => 186000],
    ['tipo' => 'venta', 'descripcion' => 'Saldo pendiente venta #7', 'debe' => 998250, 'haber' => 0],
    ['tipo' => 'pago', 'descripcion' => 'Pago venta #7', 'debe' => 0, 'haber' => 500000], // PAGO PARCIAL
    ['tipo' => 'venta', 'descripcion' => 'Venta #3 (registro histórico reconstruido)', 'debe' => 998250, 'haber' => 0],
    ['tipo' => 'pago', 'descripcion' => 'Cancelación de venta #3', 'debe' => 0, 'haber' => 998250],
];

$total_debe = 0;
$total_haber = 0;

echo "MOVIMIENTOS:\n";
foreach ($movimientos as $mov) {
    echo "{$mov['descripcion']}: DEBE {$mov['debe']}, HABER {$mov['haber']}\n";
    $total_debe += $mov['debe'];
    $total_haber += $mov['haber'];
}

echo "\nRESUMEN:\n";
echo "Total DEBE: $total_debe\n";
echo "Total HABER: $total_haber\n";
echo "Saldo real: " . ($total_debe - $total_haber) . "\n";

// El problema está en la venta #7
echo "\n=== ANÁLISIS VENTA #7 ===\n";
echo "Venta #7 por: $998.250\n";
echo "Pago realizado: $500.000 (pago parcial)\n";
echo "Saldo pendiente venta #7: " . (998250 - 500000) . "\n";

echo "\n=== SALDO CORRECTO ESPERADO ===\n";
echo "Solo considerando venta #7 no pagada completamente:\n";
echo "Saldo pendiente: -$498.250 (cliente debe este monto)\n";