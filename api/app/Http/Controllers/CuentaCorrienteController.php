<?php
namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Venta;
use App\Models\Pago;
use App\Models\MovimientoCuentaCorriente;
use Illuminate\Http\Request;

class CuentaCorrienteController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        // Si manejás permisos, descomenta:
        // $this->middleware('permission:cta_cte.show')->only('show');
    }

    /**
     * GET /api/v1/clientes/{cliente}/cuenta-corriente
     * Query params opcionales: ?desde=YYYY-MM-DD&hasta=YYYY-MM-DD
     */
    public function show(Request $request, Cliente $cliente)
    {
        $desde = $request->date('desde');
        $hasta = $request->date('hasta');

        // === Obtener movimientos desde la tabla MovimientoCuentaCorriente ===
        $query = MovimientoCuentaCorriente::query()
            ->where('cliente_id', $cliente->id)
            ->with(['venta' => function($q) {
                $q->withTrashed()->with('items.producto');
            }]);

        if ($desde) $query->whereDate('fecha', '>=', $desde);
        if ($hasta) $query->whereDate('fecha', '<=', $hasta);
        
        $movimientosRaw = $query->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        
        \Log::info("Cuenta Corriente - Cliente #{$cliente->id}", [
            'total_movimientos' => $movimientosRaw->count(),
            'ids' => $movimientosRaw->pluck('id')->toArray()
        ]);

        $movimientos = $movimientosRaw->map(function($mov) {
                // Usar campos debe/haber directamente de BD (no recalcular)
                $debe = (float)$mov->debe;
                $haber = (float)$mov->haber;
                
                // Obtener detalles de la venta si existe (ya cargada con eager loading)
                $detalles = null;
                if ($mov->tipo === 'venta' && $mov->venta) {
                    if ($mov->venta->items) {
                        $detalles = $mov->venta->items->map(function($detalle) {
                            return [
                                'producto' => $detalle->producto->nombre ?? 'Producto desconocido',
                                'cantidad' => $detalle->cantidad,
                                'precio_unitario' => (float)$detalle->precio_unitario,
                                'subtotal' => (float)$detalle->subtotal,
                            ];
                        })->toArray();
                    }
                }
                
                return [
                    'id'            => $mov->id,
                    'fecha'         => $mov->fecha->format('Y-m-d H:i:s'),
                    'tipo'          => $mov->tipo,
                    'referencia_id' => $mov->referencia_id,
                    'descripcion'   => $mov->descripcion,
                    'debe'          => $debe,
                    'haber'         => $haber,
                    'detalles'      => $detalles,
                ];
            })
            ->toArray();

        // Calcular saldo acumulado usando debe - haber
        $saldo = 0.0;
        $totalDebe = 0.0;
        $totalHaber = 0.0;

        foreach ($movimientos as &$m) {
            $totalDebe += $m['debe'];
            $totalHaber += $m['haber'];
            
            // DEBE incrementa, HABER decrementa
            $saldo += $m['debe'] - $m['haber'];
            $m['saldo_acumulado'] = round($saldo, 2);
        }
        unset($m);

        // Obtener el saldo calculado en tiempo real
        $saldoCalculado = $cliente->calcularSaldoReal();

        return response()->json([
            'cliente' => [
                'id'              => $cliente->id,
                'nombre'          => $cliente->nombre,
                'apellido'        => $cliente->apellido,
                'limite_credito'  => (float)$cliente->limite_credito,
                'saldo_actual'    => $saldoCalculado, // Usar saldo calculado
                'saldo_bd'        => (float)$cliente->saldo_actual, // Para referencia
            ],
            'filtros' => [
                'desde' => $desde?->toDateString(),
                'hasta' => $hasta?->toDateString(),
            ],
            'resumen' => [
                'total_debe'   => round($totalDebe, 2),
                'total_haber'  => round($totalHaber, 2),
                'saldo_actual' => round($saldo, 2),
            ],
            'movimientos' => $movimientos,
        ]);
    }

    /**
     * POST /api/v1/cuentas-corrientes/recalcular
     * Recalcula el saldo_actual de todos los clientes basándose en sus movimientos de cuenta corriente
     */
    public function recalcular(Request $request)
    {
        $clientes = Cliente::all();
        $actualizados = 0;
        $errores = [];

        foreach ($clientes as $cliente) {
            try {
                // Usar el método del modelo para recalcular
                $actualizado = $cliente->recalcularSaldo();
                
                if ($actualizado) {
                    $actualizados++;
                }
            } catch (\Exception $e) {
                $errores[] = [
                    'cliente_id' => $cliente->id,
                    'nombre' => $cliente->nombre . ' ' . $cliente->apellido,
                    'error' => $e->getMessage()
                ];
                \Log::error("Error recalculando cliente #{$cliente->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Recalculación completada',
            'total_clientes' => $clientes->count(),
            'actualizados' => $actualizados,
            'sin_cambios' => $clientes->count() - $actualizados - count($errores),
            'errores' => $errores
        ]);
    }

    /**
     * POST /api/v1/clientes/{cliente}/cuenta-corriente/pagos
     * Registra un pago de cuenta corriente (reduce la deuda del cliente)
     * DISTRIBUYE el pago FIFO entre las ventas pendientes
     */
    public function registrarPago(Request $request, Cliente $cliente)
    {
        $request->validate([
            'monto' => 'required|numeric|gt:0',
            'metodo_pago_id' => 'required|integer|exists:metodos_pago,id',
            'fecha_pago' => 'nullable|date',
            'observaciones' => 'nullable|string|max:500',
        ]);

        // Validación: No se puede seleccionar "Cuenta Corriente" como método de pago
        $metodoPago = \App\Models\MetodoPago::find($request->metodo_pago_id);
        if (!$metodoPago) {
            return response()->json([
                'message' => 'Método de pago no válido'
            ], 422);
        }
        
        if (strtolower($metodoPago->nombre) === 'cuenta corriente') {
            return response()->json([
                'message' => 'No se puede pagar una cuenta corriente con cuenta corriente. Seleccione efectivo, transferencia, cheque u otro método.'
            ], 422);
        }
        
        // Validación: El monto no puede ser mayor al saldo actual
        $saldoActual = $cliente->calcularSaldoReal();
        $monto = round((float)$request->monto, 2);
        
        if ($monto > $saldoActual + 0.01) {
            return response()->json([
                'message' => sprintf(
                    'El monto ($%s) no puede ser mayor al saldo actual ($%s)',
                    number_format($monto, 2),
                    number_format($saldoActual, 2)
                )
            ], 422);
        }
        
        // Usar el servicio para distribuir el pago FIFO
        $service = new \App\Services\CuentaCorrienteService();
        
        $resultado = $service->registrarPagoCliente(
            clienteId: $cliente->id,
            montoPago: $monto,
            fecha: $request->fecha_pago ?? now()->format('Y-m-d'),
            metodoPagoId: $request->metodo_pago_id,
            observaciones: $request->observaciones
        );
        
        $clienteActualizado = $resultado['cliente'];
        
        \Log::info("Pago de cuenta corriente registrado con distribución FIFO", [
            'cliente_id' => $cliente->id,
            'monto' => $monto,
            'metodo_pago' => $metodoPago->nombre,
            'saldo_anterior' => $saldoActual,
            'saldo_nuevo' => $clienteActualizado->saldo_actual,
            'ventas_afectadas' => $resultado['ventas_afectadas'],
            'movimientos_creados' => count($resultado['movimientos_creados']),
        ]);
        
        return response()->json([
            'message' => 'Pago registrado exitosamente',
            'movimientos_creados' => count($resultado['movimientos_creados']),
            'ventas_afectadas' => $resultado['ventas_afectadas'],
            'cliente' => [
                'id' => $clienteActualizado->id,
                'saldo_actual' => (float)$clienteActualizado->saldo_actual,
                'limite_credito' => (float)$clienteActualizado->limite_credito,
                'credito_disponible' => (float)$clienteActualizado->limite_credito - (float)$clienteActualizado->saldo_actual,
            ],
        ], 201);
    }

    /**
     * Recalcula el saldo de un cliente específico basándose en sus movimientos
     */
    private function recalcularSaldoCliente(Cliente $cliente)
    {
        try {
            $movimientos = MovimientoCuentaCorriente::where('cliente_id', $cliente->id)->get();
            
            $total_debe = $movimientos->sum('debe');
            $total_haber = $movimientos->sum('haber'); 
            // Saldo actual = DEUDA del cliente (DEBE - HABER)
            // Debe ser positivo cuando el cliente debe dinero
            $saldo_calculado = $total_debe - $total_haber;
            
            if ($cliente->saldo_actual != $saldo_calculado) {
                \Log::info("Actualizando saldo cliente #{$cliente->id}: {$cliente->saldo_actual} -> {$saldo_calculado}");
                $cliente->update(['saldo_actual' => $saldo_calculado]);
            }
        } catch (\Exception $e) {
            \Log::error("Error recalculando saldo cliente #{$cliente->id}: " . $e->getMessage());
        }
    }
}
