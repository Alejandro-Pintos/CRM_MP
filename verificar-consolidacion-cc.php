<?php

/**
 * Script de Verificación Manual - Consolidación CC
 * 
 * Ejecutar: php api/verificar-consolidacion-cc.php
 * 
 * Este script prueba manualmente:
 * 1. Cálculo de saldo con debe/haber
 * 2. Validación de límite de crédito
 * 3. Consistencia entre diferentes métodos
 */

require __DIR__ . '/api/vendor/autoload.php';

$app = require_once __DIR__ . '/api/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cliente;
use App\Models\MovimientoCuentaCorriente;
use App\Services\CuentaCorrienteService;
use Illuminate\Support\Facades\DB;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICACIÓN MANUAL - CONSOLIDACIÓN CUENTA CORRIENTE       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

try {
    // Buscar cliente de prueba (Nery)
    $cliente = Cliente::where('nombre', 'Nery')->first();
    
    if (!$cliente) {
        echo "⚠️  Cliente 'Nery' no encontrado. Creando cliente de prueba...\n";
        $cliente = Cliente::create([
            'nombre' => 'Test',
            'apellido' => 'Consolidación',
            'limite_credito' => 5000000,
            'saldo_actual' => 0,
        ]);
        echo "✅ Cliente creado: #{$cliente->id}\n";
    } else {
        echo "📋 Cliente encontrado: {$cliente->nombre} {$cliente->apellido} (ID: {$cliente->id})\n";
    }
    
    echo "\n";
    echo "──────────────────────────────────────────────────────────────\n";
    echo "  DATOS ACTUALES DEL CLIENTE\n";
    echo "──────────────────────────────────────────────────────────────\n";
    
    $saldoBD = (float)$cliente->saldo_actual;
    $limite = (float)$cliente->limite_credito;
    
    echo sprintf("  Límite de crédito:     $%s\n", number_format($limite, 2, ',', '.'));
    echo sprintf("  Saldo en BD:           $%s\n", number_format($saldoBD, 2, ',', '.'));
    
    // Calcular saldo con método corregido
    $saldoCalculado = $cliente->calcularSaldoReal();
    echo sprintf("  Saldo calculado (NUEVO): $%s\n", number_format($saldoCalculado, 2, ',', '.'));
    
    $creditoDisponible = $limite - $saldoCalculado;
    echo sprintf("  Crédito disponible:    $%s\n", number_format($creditoDisponible, 2, ',', '.'));
    
    // Verificar consistencia
    echo "\n";
    if (abs($saldoBD - $saldoCalculado) > 0.01) {
        echo "⚠️  ADVERTENCIA: Saldo BD y calculado son diferentes\n";
        echo "    Diferencia: $" . number_format(abs($saldoBD - $saldoCalculado), 2, ',', '.') . "\n";
        echo "    Ejecuta: Cliente::find({$cliente->id})->recalcularSaldo()\n";
    } else {
        echo "✅ Saldo BD y calculado son consistentes\n";
    }
    
    echo "\n";
    echo "──────────────────────────────────────────────────────────────\n";
    echo "  MOVIMIENTOS DE CUENTA CORRIENTE\n";
    echo "──────────────────────────────────────────────────────────────\n";
    
    $movimientos = MovimientoCuentaCorriente::where('cliente_id', $cliente->id)
        ->orderBy('fecha')
        ->orderBy('id')
        ->get();
    
    if ($movimientos->isEmpty()) {
        echo "  (Sin movimientos)\n";
    } else {
        echo sprintf("  Total movimientos: %d\n\n", $movimientos->count());
        echo "  Fecha       Tipo    DEBE         HABER        Saldo\n";
        echo "  ──────────  ──────  ───────────  ───────────  ───────────\n";
        
        $saldo = 0;
        $totalDebe = 0;
        $totalHaber = 0;
        
        foreach ($movimientos as $mov) {
            $debe = (float)$mov->debe;
            $haber = (float)$mov->haber;
            
            $totalDebe += $debe;
            $totalHaber += $haber;
            $saldo += $debe - $haber;
            
            echo sprintf(
                "  %s  %-6s  %11s  %11s  %11s\n",
                $mov->fecha->format('Y-m-d'),
                $mov->tipo,
                $debe > 0 ? '$' . number_format($debe, 2, ',', '.') : '-',
                $haber > 0 ? '$' . number_format($haber, 2, ',', '.') : '-',
                '$' . number_format($saldo, 2, ',', '.')
            );
        }
        
        echo "  ──────────────────────────────────────────────────────────\n";
        echo sprintf(
            "  TOTALES:            %11s  %11s  %11s\n",
            '$' . number_format($totalDebe, 2, ',', '.'),
            '$' . number_format($totalHaber, 2, ',', '.'),
            '$' . number_format($saldo, 2, ',', '.')
        );
    }
    
    echo "\n";
    echo "──────────────────────────────────────────────────────────────\n";
    echo "  VERIFICACIÓN DE INVARIANTES\n";
    echo "──────────────────────────────────────────────────────────────\n";
    echo "\n";
    
    // INVARIANTE #1: 0 ≤ saldo ≤ límite
    if ($saldoCalculado >= 0 && $saldoCalculado <= $limite + 0.01) {
        echo "✅ INVARIANTE #1: 0 ≤ saldo ≤ límite\n";
    } else {
        echo "❌ INVARIANTE #1 VIOLADO: saldo fuera de rango válido\n";
    }
    
    // INVARIANTE #2: disponible ≥ 0
    if ($creditoDisponible >= -0.01) {
        echo "✅ INVARIANTE #2: crédito disponible ≥ 0\n";
    } else {
        echo "❌ INVARIANTE #2 VIOLADO: crédito disponible negativo\n";
    }
    
    // INVARIANTE #3: saldo = debe - haber
    $debeTotal = MovimientoCuentaCorriente::where('cliente_id', $cliente->id)
        ->where('tipo', 'venta')
        ->sum('debe');
    $haberTotal = MovimientoCuentaCorriente::where('cliente_id', $cliente->id)
        ->where('tipo', 'pago')
        ->sum('haber');
    $saldoFormula = $debeTotal - $haberTotal;
    
    if (abs($saldoCalculado - $saldoFormula) < 0.01) {
        echo "✅ INVARIANTE #3: saldo = DEBE - HABER\n";
    } else {
        echo "❌ INVARIANTE #3 VIOLADO: saldo no coincide con fórmula\n";
    }
    
    echo "\n";
    echo "──────────────────────────────────────────────────────────────\n";
    echo "  COMPARACIÓN CON SERVICIO CuentaCorrienteService\n";
    echo "──────────────────────────────────────────────────────────────\n";
    echo "\n";
    
    // Obtener deuda por venta usando el servicio
    $service = app(CuentaCorrienteService::class);
    $deudasPorVenta = DB::table('movimientos_cuenta_corriente')
        ->where('cliente_id', $cliente->id)
        ->whereNotNull('venta_id')
        ->select('venta_id')
        ->distinct()
        ->get();
    
    if ($deudasPorVenta->isEmpty()) {
        echo "  (Sin ventas con cuenta corriente)\n";
    } else {
        $totalDeudaVentas = 0;
        foreach ($deudasPorVenta as $row) {
            $deudaVenta = $service->calcularDeudaCCVenta($row->venta_id);
            if ($deudaVenta > 0.01) {
                echo sprintf("  Venta #%d: $%s\n", $row->venta_id, number_format($deudaVenta, 2, ',', '.'));
                $totalDeudaVentas += $deudaVenta;
            }
        }
        
        echo "\n";
        echo sprintf("  Total deuda (servicio): $%s\n", number_format($totalDeudaVentas, 2, ',', '.'));
        echo sprintf("  Saldo calculado (modelo): $%s\n", number_format($saldoCalculado, 2, ',', '.'));
        
        if (abs($totalDeudaVentas - $saldoCalculado) < 0.01) {
            echo "\n✅ CONSISTENCIA: Servicio y Modelo coinciden\n";
        } else {
            echo "\n⚠️  INCONSISTENCIA: Servicio y Modelo difieren\n";
            echo "    Diferencia: $" . number_format(abs($totalDeudaVentas - $saldoCalculado), 2, ',', '.') . "\n";
        }
    }
    
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║  VERIFICACIÓN COMPLETADA                                     ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    
} catch (Exception $e) {
    echo "\n";
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    echo "\n";
    exit(1);
}
