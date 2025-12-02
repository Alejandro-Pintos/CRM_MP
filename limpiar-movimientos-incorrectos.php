<?php

require __DIR__ . '/api/vendor/autoload.php';
$app = require_once __DIR__ . '/api/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cliente;
use App\Models\MovimientoCuentaCorriente;
use Illuminate\Support\Facades\DB;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  LIMPIEZA DE MOVIMIENTOS INCORRECTOS                         ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

$cliente = Cliente::find(3);

echo "Cliente: {$cliente->nombre} {$cliente->apellido} (ID: {$cliente->id})\n";
echo "Saldo antes: \${$cliente->saldo_actual}\n";
echo "\n";

// Buscar movimientos problemáticos
$pagosHuerfanos = MovimientoCuentaCorriente::where('cliente_id', $cliente->id)
    ->where('tipo', 'pago')
    ->whereNull('venta_id')
    ->get();

echo "MOVIMIENTOS PROBLEMÁTICOS ENCONTRADOS:\n";
echo "──────────────────────────────────────────────────────────────\n";

foreach ($pagosHuerfanos as $mov) {
    echo sprintf(
        "ID:%-3d | %s | HABER:%s | DESC: %s\n",
        $mov->id,
        $mov->fecha->format('Y-m-d'),
        number_format($mov->haber, 0),
        $mov->descripcion ?? '(sin descripción)'
    );
}

echo "\nTotal pagos huérfanos: " . $pagosHuerfanos->count() . "\n";
echo "Monto total: $" . number_format($pagosHuerfanos->sum('haber'), 0) . "\n";
echo "\n";

// Confirmar eliminación
echo "¿Eliminar estos movimientos? (s/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$respuesta = trim($line);

if (strtolower($respuesta) === 's') {
    echo "\n";
    echo "Eliminando movimientos...\n";
    
    DB::beginTransaction();
    try {
        $eliminados = 0;
        foreach ($pagosHuerfanos as $mov) {
            echo "  - Eliminando movimiento ID {$mov->id}... ";
            $mov->delete();
            $eliminados++;
            echo "✓\n";
        }
        
        // Recalcular saldo
        echo "\nRecalculando saldo del cliente...\n";
        $cliente->recalcularSaldo();
        $cliente->refresh();
        
        DB::commit();
        
        echo "\n";
        echo "✅ Limpieza completada exitosamente\n";
        echo "   Movimientos eliminados: {$eliminados}\n";
        echo "   Saldo después: \${$cliente->saldo_actual}\n";
        echo "\n";
        
        // Verificar que ahora esté bien
        $saldoCalculado = $cliente->calcularSaldoReal();
        if ($saldoCalculado >= 0 && $saldoCalculado <= $cliente->limite_credito) {
            echo "✅ El saldo ahora está dentro del rango válido (0 a límite)\n";
        } else {
            echo "⚠️  El saldo aún está fuera del rango válido\n";
            echo "   Saldo: \${$saldoCalculado}\n";
            echo "   Límite: \${$cliente->limite_credito}\n";
        }
        
    } catch (Exception $e) {
        DB::rollBack();
        echo "\n";
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "\nOperación cancelada.\n";
}

echo "\n";
