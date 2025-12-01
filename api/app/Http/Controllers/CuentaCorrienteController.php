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
            ->where('cliente_id', $cliente->id);

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
                $monto = (float)$mov->monto;
                
                // Para ventas: monto positivo = DEBE (aumenta deuda)
                // Para pagos: monto positivo = HABER (reduce deuda)
                $debe = 0;
                $haber = 0;
                $montoParaSaldo = 0;
                
                if ($mov->tipo === 'venta') {
                    $debe = abs($monto);
                    $montoParaSaldo = abs($monto);
                } else { // pago
                    $haber = abs($monto);
                    $montoParaSaldo = -abs($monto);
                }
                
                // Obtener detalles de la venta si existe
                $detalles = null;
                if ($mov->tipo === 'venta' && $mov->referencia_id) {
                    $venta = \App\Models\Venta::withTrashed()->with('items.producto')->find($mov->referencia_id);
                    if ($venta && $venta->items) {
                        $detalles = $venta->items->map(function($detalle) {
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
                    'monto'         => $montoParaSaldo,
                    'detalles'      => $detalles,
                ];
            })
            ->toArray();

        // Calcular saldo acumulado
        $saldo = 0.0;
        $totalDebe = 0.0;
        $totalHaber = 0.0;

        foreach ($movimientos as &$m) {
            $totalDebe += $m['debe'];
            $totalHaber += $m['haber'];
            $saldo += $m['monto'];
            $m['saldo_acumulado'] = round($saldo, 2);
        }
        unset($m);

        return response()->json([
            'cliente' => [
                'id'              => $cliente->id,
                'nombre'          => $cliente->nombre,
                'apellido'        => $cliente->apellido,
                'limite_credito'  => (float)$cliente->limite_credito,
                'saldo_actual'    => (float)$cliente->saldo_actual,
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
     * Recalcula el saldo_actual de todos los clientes basándose en los pagos registrados como "Cuenta Corriente"
     */
    public function recalcular(Request $request)
    {
        $clientes = Cliente::all();
        $actualizados = 0;
        $errores = [];
        $cuentaCorrienteId = \App\Models\MetodoPago::where('nombre', 'Cuenta Corriente')->value('id');

        foreach ($clientes as $cliente) {
            try {
                // El saldo_actual debería ser igual a la suma de todos los registros de "Cuenta Corriente"
                // que aún no han sido pagados con métodos reales
                
                $totalCuentaCorriente = Pago::whereHas('venta', function($q) use ($cliente) {
                        $q->where('cliente_id', $cliente->id);
                    })
                    ->where('metodo_pago_id', $cuentaCorrienteId)
                    ->sum('monto');
                
                // El saldo debería ser el total en cuenta corriente (deuda pendiente)
                // Si es 0, significa que no hay deuda
                // Si es negativo, algo está mal (no debería pasar)
                $saldoCalculado = round((float)$totalCuentaCorriente, 2);
                
                // Actualizar solo si hay diferencia significativa
                if (abs($cliente->saldo_actual - $saldoCalculado) > 0.01) {
                    $saldoAnterior = $cliente->saldo_actual;
                    $cliente->saldo_actual = $saldoCalculado;
                    $cliente->save();
                    
                    $actualizados++;
                    \Log::info("Cliente #{$cliente->id} ({$cliente->nombre} {$cliente->apellido}) actualizado: {$saldoAnterior} → {$saldoCalculado}");
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
