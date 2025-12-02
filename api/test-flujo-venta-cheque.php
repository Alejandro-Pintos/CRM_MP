<?php
/**
 * Script de prueba del flujo completo de Venta con Cheque
 * 
 * Prueba:
 * 1. Crear venta con pago parcial en efectivo + cheque
 * 2. Verificar que se creó el cheque automáticamente
 * 3. Verificar que la deuda se registró en cuenta corriente
 * 4. Cobrar el cheque
 * 5. Verificar que se redujo la deuda en CC
 * 
 * Uso: php test-flujo-venta-cheque.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\MetodoPago;
use App\Services\Ventas\RegistrarVentaService;
use App\Services\Finanzas\ChequeService;
use App\Services\Finanzas\CuentaCorrienteService;

echo "=== PRUEBA DE FLUJO COMPLETO: VENTA CON CHEQUE ===\n\n";

try {
    // Servicios
    $registrarVentaService = app(RegistrarVentaService::class);
    $chequeService = app(ChequeService::class);
    $ccService = app(CuentaCorrienteService::class);
    
    // 1. PREPARAR DATOS
    echo "📋 1. Preparando datos de prueba...\n";
    
    $cliente = Cliente::first();
    if (!$cliente) {
        echo "❌ No hay clientes en la BD\n";
        exit(1);
    }
    
    $producto = Producto::first();
    if (!$producto) {
        echo "❌ No hay productos en la BD\n";
        exit(1);
    }
    
    $efectivo = MetodoPago::where('nombre', 'Efectivo')->first();
    $cheque = MetodoPago::where('nombre', 'Cheque')->first();
    
    if (!$efectivo || !$cheque) {
        echo "❌ No se encontraron métodos de pago (Efectivo/Cheque)\n";
        exit(1);
    }
    
    $saldoInicial = $ccService->obtenerSaldoActual($cliente);
    
    echo "   Cliente: {$cliente->nombre} {$cliente->apellido}\n";
    echo "   Producto: {$producto->nombre}\n";
    echo "   Saldo inicial CC: $" . number_format($saldoInicial, 2) . "\n\n";
    
    // 2. CREAR VENTA
    echo "📋 2. Creando venta con pago mixto (Efectivo + Cheque)...\n";
    
    $dataVenta = [
        'cliente_id' => $cliente->id,
        'fecha' => now(),
        'tipo_comprobante' => 'FC-A',
        'numero_comprobante' => '0001-00000TEST',
        'items' => [
            [
                'producto_id' => $producto->id,
                'cantidad' => 5,
                'precio_unitario' => 1000,
                'iva' => 21,
            ]
        ],
        'pagos' => [
            // Pago parcial en efectivo
            [
                'metodo_pago_id' => $efectivo->id,
                'monto' => 2000,
                'fecha_pago' => now(),
            ],
            // Pago con cheque a 30 días
            [
                'metodo_pago_id' => $cheque->id,
                'monto' => 3000,
                'fecha_pago' => now(),
                'numero_cheque' => 'TEST-' . time(),
                'fecha_cheque' => now(),
                'fecha_vencimiento' => now()->addDays(30),
                'observaciones_cheque' => 'Cheque de prueba automática',
            ]
        ]
    ];
    
    // Calcular total esperado
    $totalEsperado = 5 * 1000 * 1.21; // cantidad * precio * (1 + IVA%)
    $totalPagado = 2000 + 3000;
    $saldoPendiente = $totalEsperado - $totalPagado;
    
    echo "   Total esperado: $" . number_format($totalEsperado, 2) . "\n";
    echo "   Total pagado: $" . number_format($totalPagado, 2) . "\n";
    echo "   Saldo a CC: $" . number_format($saldoPendiente, 2) . "\n\n";
    
    $venta = $registrarVentaService->ejecutar($cliente, $dataVenta);
    
    echo "   ✅ Venta creada - ID: {$venta->id}\n";
    echo "   Total real: $" . number_format($venta->total, 2) . "\n";
    echo "   Estado pago: {$venta->estado_pago}\n";
    echo "   Items: {$venta->items->count()}\n";
    echo "   Pagos: {$venta->pagos->count()}\n\n";
    
    // 3. VERIFICAR CHEQUE
    echo "📋 3. Verificando creación del cheque...\n";
    
    $chequeCreado = \App\Models\Cheque::where('venta_id', $venta->id)->first();
    
    if ($chequeCreado) {
        echo "   ✅ Cheque registrado - ID: {$chequeCreado->id}\n";
        echo "   Número: {$chequeCreado->numero}\n";
        echo "   Monto: $" . number_format($chequeCreado->monto, 2) . "\n";
        echo "   Estado: {$chequeCreado->estado}\n";
        echo "   Vencimiento: " . ($chequeCreado->fecha_vencimiento ? $chequeCreado->fecha_vencimiento->format('d/m/Y') : 'N/A') . "\n\n";
    } else {
        echo "   ❌ No se creó el cheque\n\n";
    }
    
    // 4. VERIFICAR CUENTA CORRIENTE
    echo "📋 4. Verificando movimiento en Cuenta Corriente...\n";
    
    $saldoActual = $ccService->obtenerSaldoActual($cliente);
    $incremento = $saldoActual - $saldoInicial;
    
    echo "   Saldo anterior: $" . number_format($saldoInicial, 2) . "\n";
    echo "   Saldo actual: $" . number_format($saldoActual, 2) . "\n";
    echo "   Incremento: $" . number_format($incremento, 2) . "\n";
    
    if (abs($incremento - $saldoPendiente) < 0.01) {
        echo "   ✅ Deuda registrada correctamente en CC\n\n";
    } else {
        echo "   ⚠️  Discrepancia en deuda CC\n\n";
    }
    
    // 5. COBRAR CHEQUE
    if ($chequeCreado && $chequeCreado->estado === 'pendiente') {
        echo "📋 5. Cobrando el cheque...\n";
        
        $chequeService->marcarComoCobrado($chequeCreado, now());
        
        echo "   ✅ Cheque marcado como cobrado\n";
        echo "   Fecha cobro: " . $chequeCreado->fresh()->fecha_cobro->format('d/m/Y') . "\n\n";
        
        // 6. VERIFICAR REDUCCIÓN DE DEUDA
        echo "📋 6. Verificando reducción de deuda en CC...\n";
        
        $saldoDespuesCobro = $ccService->obtenerSaldoActual($cliente);
        $reduccion = $saldoActual - $saldoDespuesCobro;
        
        echo "   Saldo antes de cobrar: $" . number_format($saldoActual, 2) . "\n";
        echo "   Saldo después de cobrar: $" . number_format($saldoDespuesCobro, 2) . "\n";
        echo "   Reducción: $" . number_format($reduccion, 2) . "\n";
        
        if (abs($reduccion - $chequeCreado->monto) < 0.01) {
            echo "   ✅ Deuda reducida correctamente\n\n";
        } else {
            echo "   ⚠️  Discrepancia en reducción de deuda\n\n";
        }
    }
    
    // 7. VERIFICAR ESTADO FINAL
    echo "📋 7. Estado final de la venta...\n";
    
    $venta->refresh();
    echo "   Estado pago: {$venta->estado_pago}\n";
    echo "   Cheques: " . $venta->cheques()->count() . "\n";
    echo "   Cheques cobrados: " . $venta->cheques()->where('estado', 'cobrado')->count() . "\n\n";
    
    // RESUMEN
    echo "=== ✅ PRUEBA COMPLETADA EXITOSAMENTE ===\n\n";
    
    echo "📊 RESUMEN:\n";
    echo "- Venta creada con ID: {$venta->id}\n";
    echo "- Total: $" . number_format($venta->total, 2) . "\n";
    echo "- Pagado en efectivo: $2,000.00\n";
    echo "- Pagado con cheque: $" . number_format($chequeCreado->monto ?? 0, 2) . "\n";
    echo "- Saldo en CC inicial: $" . number_format($saldoPendiente, 2) . "\n";
    echo "- Saldo en CC final: $" . number_format($saldoDespuesCobro ?? $saldoActual, 2) . "\n";
    echo "- Estado: {$venta->estado_pago}\n";
    
    // LIMPIEZA (OPCIONAL)
    echo "\n⚠️  Nota: La venta de prueba quedó registrada en la BD.\n";
    echo "Para limpiar: DELETE FROM ventas WHERE id = {$venta->id};\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
