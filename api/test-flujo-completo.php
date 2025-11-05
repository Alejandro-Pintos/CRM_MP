<?php
/**
 * Test del Flujo Completo: Pedido → Venta → Pago
 * 
 * Este script prueba:
 * 1. Crear un pedido con productos
 * 2. Convertir el pedido a venta con pago parcial
 * 3. Verificar movimientos en cuenta corriente
 * 4. Registrar pago adicional
 * 5. Validar actualización de saldos
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Venta;
use App\Models\MetodoPago;
use App\Services\VentaService;
use App\Services\PagoService;
use Illuminate\Support\Facades\DB;

echo "\n" . str_repeat("=", 80) . "\n";
echo "TEST FLUJO COMPLETO: PEDIDO → VENTA → PAGO\n";
echo str_repeat("=", 80) . "\n\n";

try {
    DB::beginTransaction();

    // ============================================================================
    // PASO 1: VERIFICAR/CREAR DATOS BÁSICOS
    // ============================================================================
    echo "📋 PASO 1: Preparando datos de prueba...\n";
    echo str_repeat("-", 80) . "\n";

    // Buscar o crear cliente con cuenta corriente
    $cliente = Cliente::where('email', 'test@prueba.com')->first();
    if (!$cliente) {
        $cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Pérez Test',
            'email' => 'test@prueba.com',
            'telefono' => '1234567890',
            'limite_credito' => 50000.00,
            'saldo_actual' => 0.00,
            'requiere_factura' => true,
        ]);
        echo "✅ Cliente creado: {$cliente->nombre} {$cliente->apellido} (ID: {$cliente->id})\n";
    } else {
        // Resetear saldo para la prueba
        $cliente->update(['saldo_actual' => 0.00]);
        echo "✅ Cliente encontrado: {$cliente->nombre} {$cliente->apellido} (ID: {$cliente->id})\n";
    }

    echo "   Límite de crédito: $" . number_format($cliente->limite_credito, 2) . "\n";
    echo "   Saldo actual: $" . number_format($cliente->saldo_actual, 2) . "\n";

    // Buscar o crear producto
    $producto = Producto::where('codigo', 'TEST-001')->first();
    if (!$producto) {
        $producto = Producto::create([
            'codigo' => 'TEST-001',
            'nombre' => 'Producto de Prueba',
            'descripcion' => 'Producto para testing',
            'unidad_medida' => 'u',
            'precio_compra' => 5000.00,
            'precio_venta' => 8000.00,
            'precio' => 10000.00,
            'iva' => 21.00,
            'estado' => 'activo',
        ]);
        echo "✅ Producto creado: {$producto->nombre} (ID: {$producto->id})\n";
    } else {
        echo "✅ Producto encontrado: {$producto->nombre} (ID: {$producto->id})\n";
    }
    echo "   Precio: $" . number_format($producto->precio, 2) . "\n";
    echo "   IVA: {$producto->iva}%\n";

    // Buscar o crear método de pago
    $metodoPago = MetodoPago::where('nombre', 'Efectivo')->first();
    if (!$metodoPago) {
        $metodoPago = MetodoPago::create([
            'nombre' => 'Efectivo',
            'descripcion' => 'Pago en efectivo',
            'activo' => true,
        ]);
        echo "✅ Método de pago creado: {$metodoPago->nombre}\n";
    } else {
        echo "✅ Método de pago encontrado: {$metodoPago->nombre}\n";
    }

    echo "\n";

    // ============================================================================
    // PASO 2: CREAR PEDIDO
    // ============================================================================
    echo "📦 PASO 2: Creando pedido...\n";
    echo str_repeat("-", 80) . "\n";

    $pedido = Pedido::create([
        'cliente_id' => $cliente->id,
        'fecha_pedido' => now(),
        'fecha_entrega_aprox' => now()->addDays(7),
        'estado' => 'pendiente',
        'observaciones' => 'Pedido de prueba - Test automatizado',
    ]);

    DetallePedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'cantidad' => 2,
        'precio_compra' => $producto->precio_compra,
        'precio_venta' => $producto->precio_venta,
        'porcentaje_iva' => $producto->iva,
        'precio_unitario' => $producto->precio,
        'observaciones' => null,
    ]);

    echo "✅ Pedido creado (ID: {$pedido->id})\n";
    echo "   Cliente: {$cliente->nombre} {$cliente->apellido}\n";
    echo "   Estado: {$pedido->estado}\n";
    echo "   Items: 2 x {$producto->nombre} = $" . number_format($producto->precio * 2, 2) . "\n";
    $totalPedido = $producto->precio * 2;
    echo "   TOTAL PEDIDO: $" . number_format($totalPedido, 2) . "\n";
    echo "\n";

    // ============================================================================
    // PASO 3: CONVERTIR PEDIDO A VENTA CON PAGO PARCIAL
    // ============================================================================
    echo "💰 PASO 3: Convirtiendo pedido a venta (pago parcial)...\n";
    echo str_repeat("-", 80) . "\n";

    $montoParcial = 12000.00;
    $saldoPendiente = $totalPedido - $montoParcial;

    $ventaData = [
        'cliente_id' => $cliente->id,
        'fecha' => now(),
        'tipo_comprobante' => 'FACTURA_A',
        'pedido_id' => $pedido->id,
        'items' => [
            [
                'producto_id' => $producto->id,
                'cantidad' => 2,
                'precio_unitario' => $producto->precio,
                'iva' => $producto->iva,
            ]
        ],
        'pagos' => [
            [
                'metodo_pago_id' => $metodoPago->id,
                'monto' => $montoParcial,
                'fecha_pago' => now(),
            ]
        ],
    ];

    $ventaService = new VentaService();
    $venta = $ventaService->crearVenta($ventaData, 1); // usuario_id = 1
    $venta->load('pagos.metodo'); // Cargar relación de pagos con método

    echo "✅ Venta creada (ID: {$venta->id})\n";
    echo "   Número comprobante: {$venta->tipo_comprobante} {$venta->numero_comprobante}\n";
    echo "   Total venta: $" . number_format($venta->total, 2) . "\n";
    echo "   Estado pago: {$venta->estado_pago}\n";
    echo "\n";

    echo "📝 Pagos registrados:\n";
    foreach ($venta->pagos as $pago) {
        echo "   - Método: " . ($pago->metodo ? $pago->metodo->nombre : 'N/A') . " | Monto: $" . number_format($pago->monto, 2) . "\n";
    }
    echo "   TOTAL PAGADO: $" . number_format($montoParcial, 2) . "\n";
    echo "   SALDO PENDIENTE: $" . number_format($saldoPendiente, 2) . "\n";
    echo "\n";

    // Verificar cliente actualizado
    $cliente->refresh();
    echo "👤 Estado del Cliente:\n";
    echo "   Saldo actual: $" . number_format($cliente->saldo_actual, 2) . "\n";
    echo "   Crédito disponible: $" . number_format($cliente->limite_credito - $cliente->saldo_actual, 2) . "\n";
    echo "\n";

    // Verificar pedido actualizado
    $pedido->refresh();
    echo "📦 Estado del Pedido:\n";
    echo "   Estado: {$pedido->estado}\n";
    echo "   Venta asociada: #{$pedido->venta_id}\n";
    echo "\n";

    // ============================================================================
    // PASO 4: VERIFICAR MOVIMIENTOS DE CUENTA CORRIENTE
    // ============================================================================
    echo "📊 PASO 4: Verificando movimientos de cuenta corriente...\n";
    echo str_repeat("-", 80) . "\n";

    $movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', $cliente->id)
        ->orderBy('fecha', 'asc')
        ->get();

    echo "Movimientos registrados: {$movimientos->count()}\n\n";

    $saldoAcumulado = 0;
    echo sprintf("%-12s %-10s %-40s %-12s %-12s %-12s\n", 
        "FECHA", "TIPO", "DESCRIPCIÓN", "MONTO", "REF", "SALDO");
    echo str_repeat("-", 80) . "\n";

    foreach ($movimientos as $mov) {
        $saldoAcumulado += $mov->monto;
        echo sprintf("%-12s %-10s %-40s %12s %12s %12s\n",
            $mov->fecha->format('Y-m-d'),
            strtoupper($mov->tipo),
            substr($mov->descripcion, 0, 38),
            ($mov->monto > 0 ? '+' : '') . number_format($mov->monto, 2),
            $mov->tipo . '#' . $mov->referencia_id,
            number_format($saldoAcumulado, 2)
        );
    }
    echo str_repeat("-", 80) . "\n";
    echo "SALDO FINAL: $" . number_format($saldoAcumulado, 2) . "\n\n";

    // Validar que el saldo acumulado coincide con el saldo del cliente
    if (abs($saldoAcumulado - $cliente->saldo_actual) > 0.01) {
        echo "❌ ERROR: Saldo acumulado ($saldoAcumulado) no coincide con saldo del cliente ({$cliente->saldo_actual})\n";
    } else {
        echo "✅ VALIDACIÓN: Saldo acumulado coincide con saldo del cliente\n";
    }
    echo "\n";

    // ============================================================================
    // PASO 5: REGISTRAR PAGO ADICIONAL
    // ============================================================================
    echo "💵 PASO 5: Registrando pago adicional...\n";
    echo str_repeat("-", 80) . "\n";

    $montoPagoAdicional = 5000.00;
    $pagoService = new PagoService();

    $pagoAdicional = $pagoService->registrarPago($venta, [
        'metodo_pago_id' => $metodoPago->id,
        'monto' => $montoPagoAdicional,
        'fecha_pago' => now(),
    ]);

    echo "✅ Pago adicional registrado (ID: {$pagoAdicional->id})\n";
    echo "   Monto: $" . number_format($pagoAdicional->monto, 2) . "\n";
    echo "\n";

    // Verificar venta actualizada
    $venta->refresh();
    $totalPagadoNuevo = $venta->pagos()->sum('monto');
    $saldoPendienteNuevo = $venta->total - $totalPagadoNuevo;

    echo "💰 Estado de la Venta:\n";
    echo "   Total venta: $" . number_format($venta->total, 2) . "\n";
    echo "   Total pagado: $" . number_format($totalPagadoNuevo, 2) . "\n";
    echo "   Saldo pendiente: $" . number_format($saldoPendienteNuevo, 2) . "\n";
    echo "   Estado pago: {$venta->estado_pago}\n";
    echo "\n";

    // Verificar cliente actualizado
    $cliente->refresh();
    echo "👤 Estado del Cliente Actualizado:\n";
    echo "   Saldo actual: $" . number_format($cliente->saldo_actual, 2) . "\n";
    echo "   Crédito disponible: $" . number_format($cliente->limite_credito - $cliente->saldo_actual, 2) . "\n";
    echo "\n";

    // ============================================================================
    // PASO 6: VERIFICAR MOVIMIENTOS FINALES
    // ============================================================================
    echo "📊 PASO 6: Movimientos finales de cuenta corriente...\n";
    echo str_repeat("-", 80) . "\n";

    $movimientos = \App\Models\MovimientoCuentaCorriente::where('cliente_id', $cliente->id)
        ->orderBy('fecha', 'asc')
        ->get();

    echo "Total movimientos: {$movimientos->count()}\n\n";

    $saldoAcumulado = 0;
    echo sprintf("%-12s %-10s %-40s %-12s %-12s %-12s\n", 
        "FECHA", "TIPO", "DESCRIPCIÓN", "MONTO", "REF", "SALDO");
    echo str_repeat("-", 80) . "\n";

    foreach ($movimientos as $mov) {
        $saldoAcumulado += $mov->monto;
        echo sprintf("%-12s %-10s %-40s %12s %12s %12s\n",
            $mov->fecha->format('Y-m-d'),
            strtoupper($mov->tipo),
            substr($mov->descripcion, 0, 38),
            ($mov->monto > 0 ? '+' : '') . number_format($mov->monto, 2),
            $mov->tipo . '#' . $mov->referencia_id,
            number_format($saldoAcumulado, 2)
        );
    }
    echo str_repeat("-", 80) . "\n";
    echo "SALDO FINAL: $" . number_format($saldoAcumulado, 2) . "\n\n";

    // ============================================================================
    // RESUMEN FINAL
    // ============================================================================
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "📈 RESUMEN FINAL DEL TEST\n";
    echo str_repeat("=", 80) . "\n\n";

    echo "✅ Pedido #{$pedido->id}: {$pedido->estado}\n";
    echo "✅ Venta #{$venta->id}: {$venta->tipo_comprobante} {$venta->numero_comprobante}\n";
    echo "✅ Total venta: $" . number_format($venta->total, 2) . "\n";
    echo "✅ Total pagado: $" . number_format($totalPagadoNuevo, 2) . "\n";
    echo "✅ Saldo pendiente: $" . number_format($saldoPendienteNuevo, 2) . "\n";
    echo "✅ Estado pago: {$venta->estado_pago}\n";
    echo "✅ Saldo cliente: $" . number_format($cliente->saldo_actual, 2) . "\n";
    echo "✅ Movimientos registrados: {$movimientos->count()}\n";

    // Validaciones finales
    echo "\n📋 VALIDACIONES:\n";
    $validaciones = [];

    // 1. Pedido debe estar en estado 'entregado'
    $validaciones[] = [
        'test' => 'Pedido estado = entregado',
        'resultado' => $pedido->estado === 'entregado',
        'esperado' => 'entregado',
        'actual' => $pedido->estado,
    ];

    // 2. Venta debe estar asociada al pedido
    $validaciones[] = [
        'test' => 'Pedido vinculado a venta',
        'resultado' => $pedido->venta_id === $venta->id,
        'esperado' => $venta->id,
        'actual' => $pedido->venta_id,
    ];

    // 3. Estado de pago debe ser 'parcial'
    $validaciones[] = [
        'test' => 'Estado pago = parcial',
        'resultado' => $venta->estado_pago === 'parcial',
        'esperado' => 'parcial',
        'actual' => $venta->estado_pago,
    ];

    // 4. Saldo cliente debe coincidir con (total - pagado)
    $esperadoSaldoCliente = $venta->total - $totalPagadoNuevo;
    $validaciones[] = [
        'test' => 'Saldo cliente correcto',
        'resultado' => abs($cliente->saldo_actual - $esperadoSaldoCliente) < 0.01,
        'esperado' => number_format($esperadoSaldoCliente, 2),
        'actual' => number_format($cliente->saldo_actual, 2),
    ];

    // 5. Debe haber 2 movimientos (1 venta + 1 pago adicional)
    // NO se cuentan los pagos en el momento de la venta porque no afectan cuenta corriente
    $validaciones[] = [
        'test' => 'Cantidad de movimientos',
        'resultado' => $movimientos->count() === 2,
        'esperado' => 2,
        'actual' => $movimientos->count(),
    ];

    $todosCorrectos = true;
    foreach ($validaciones as $val) {
        $icon = $val['resultado'] ? '✅' : '❌';
        $todosCorrectos = $todosCorrectos && $val['resultado'];
        echo "{$icon} {$val['test']}: ";
        if ($val['resultado']) {
            echo "OK\n";
        } else {
            echo "FALLO (Esperado: {$val['esperado']}, Actual: {$val['actual']})\n";
        }
    }

    echo "\n";
    if ($todosCorrectos) {
        echo "🎉 TODAS LAS VALIDACIONES PASARON CORRECTAMENTE\n";
    } else {
        echo "⚠️  ALGUNAS VALIDACIONES FALLARON\n";
    }

    echo "\n" . str_repeat("=", 80) . "\n";
    echo "¿Desea confirmar estos cambios en la base de datos? (s/n): ";
    
    // Para testing automático, hacemos rollback
    DB::rollBack();
    echo "\n❌ ROLLBACK EJECUTADO - Los cambios NO se guardaron en la base de datos\n";
    echo "   (Esto es normal para un test. Para guardar, cambia DB::rollBack() por DB::commit())\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR EN EL TEST:\n";
    echo "Mensaje: {$e->getMessage()}\n";
    echo "Archivo: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack trace:\n{$e->getTraceAsString()}\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "TEST COMPLETADO\n";
echo str_repeat("=", 80) . "\n\n";
