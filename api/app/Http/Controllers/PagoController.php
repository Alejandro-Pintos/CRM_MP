<?php
namespace App\Http\Controllers;

use App\Http\Requests\PagoStoreRequest;
use App\Http\Resources\PagoResource;
use App\Models\Pago;
use App\Models\Venta;
use App\Services\PagoService;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:pagos.index')->only(['index']);
        $this->middleware('permission:pagos.store')->only(['store']);
    }

    public function index(Venta $venta)
    {
        return PagoResource::collection(
            $venta->pagos()->with('metodoPago')->orderByDesc('fecha_pago')->get()
        );
    }

    public function store(PagoStoreRequest $request, Venta $venta, PagoService $service)
    {
        $pago = $service->registrarPago($venta, $request->validated());
        return (new PagoResource($pago))->response()->setStatusCode(201);
    }

    /**
     * Actualizar datos de un cheque pendiente
     */
    public function update(Request $request, Pago $pago)
    {
        $request->validate([
            'numero_cheque' => 'nullable|string|max:50',
            'fecha_cheque' => 'nullable|date',
            'fecha_cobro' => 'nullable|date',
            'observaciones_cheque' => 'nullable|string|max:500',
        ]);

        // Solo se pueden editar cheques pendientes
        if ($pago->estado_cheque !== 'pendiente') {
            return response()->json([
                'message' => 'Solo se pueden editar cheques pendientes'
            ], 422);
        }

        $pago->update($request->only([
            'numero_cheque',
            'fecha_cheque',
            'fecha_cobro',
            'observaciones_cheque'
        ]));

        return new PagoResource($pago->load('metodoPago'));
    }

    /**
     * Actualizar el estado de un cheque (cobrado/rechazado)
     */
    public function actualizarEstadoCheque(Request $request, Pago $pago)
    {
        $request->validate([
            'estado_cheque' => 'required|in:cobrado,rechazado',
            'fecha_cobro' => 'nullable|date',
            'observaciones_cheque' => 'nullable|string|max:500',
        ]);

        // Solo se puede actualizar si es un cheque pendiente
        if ($pago->estado_cheque !== 'pendiente') {
            return response()->json([
                'message' => 'Este pago no es un cheque pendiente o ya fue procesado'
            ], 422);
        }

        return \DB::transaction(function () use ($request, $pago) {
            $venta = $pago->venta;
            $cliente = \App\Models\Cliente::lockForUpdate()->findOrFail($venta->cliente_id);
            
            $estadoAnterior = $pago->estado_cheque;
            $nuevoEstado = $request->estado_cheque;
            
            $pago->estado_cheque = $nuevoEstado;
            $pago->fecha_cobro = $request->fecha_cobro ?? now();
            
            if ($request->has('observaciones_cheque')) {
                $pago->observaciones_cheque = $request->observaciones_cheque;
            }
            
            $pago->save();

            // Si el cheque fue COBRADO, reducir el saldo del cliente
            if ($nuevoEstado === 'cobrado') {
                $monto = (float)$pago->monto;
                
                // Reducir saldo actual (lógica original)
                $cliente->saldo_actual = round((float)$cliente->saldo_actual - $monto, 2);
                $cliente->save();

                // Crear movimiento de cuenta corriente
                if ((float)$cliente->limite_credito > 0) {
                    \App\Models\MovimientoCuentaCorriente::create([
                        'cliente_id'   => $cliente->id,
                        'tipo'         => 'pago',
                        'referencia_id'=> $pago->id,
                        'monto'        => -abs($monto), // Negativo = reduce deuda
                        'debe'         => 0,
                        'haber'        => abs($monto),  // Pago = HABER (reduce deuda)
                        'fecha'        => $pago->fecha_cobro,
                        'descripcion'  => "Cobro de cheque #{$pago->numero_cheque} - Venta #{$venta->id}",
                    ]);
                }

                \Log::info("Cheque cobrado: Pago #{$pago->id}, Cliente #{$cliente->id}, Monto: {$monto}");
            }
            
            // Si el cheque fue RECHAZADO, el dinero sigue pendiente
            if ($nuevoEstado === 'rechazado') {
                \Log::warning("Cheque rechazado: Pago #{$pago->id}, Venta #{$venta->id}, Motivo: {$request->observaciones_cheque}");
                
                // Opcional: Podrías incrementar el saldo_actual si quieres que vuelva a deuda
                // Por ahora, el cheque rechazado simplemente queda marcado como tal
            }

            // Recargar la venta para actualizar el estado
            $venta->load('pagos');
            $venta->save(); // Esto dispara el accessor estadoPago

            return new \App\Http\Resources\PagoResource($pago->load('metodoPago'));
        });
    }

    /**
     * Consolidar pagos de una venta
     * Elimina registros de cuenta corriente si hay suficientes pagos reales para cubrirlos
     */
    public function consolidarPagos(Venta $venta)
    {
        return \DB::transaction(function () use ($venta) {
            $cuentaCorrienteId = \App\Models\MetodoPago::where('nombre', 'Cuenta Corriente')->value('id');
            
            if (!$cuentaCorrienteId) {
                return response()->json(['message' => 'No se encontró el método de pago Cuenta Corriente'], 404);
            }

            // Calcular totales
            $totalPagadoReal = (float) $venta->pagos()
                ->where('metodo_pago_id', '!=', $cuentaCorrienteId)
                ->where(function($query) {
                    $query->whereNull('estado_cheque')
                          ->orWhere('estado_cheque', 'cobrado');
                })
                ->sum('monto');
            
            $totalCuentaCorriente = (float) $venta->pagos()
                ->where('metodo_pago_id', $cuentaCorrienteId)
                ->sum('monto');
            
            $total = (float) $venta->total;
            $saldoSinCC = $total - $totalPagadoReal;
            
            // Si los pagos reales cubren todo (incluyendo lo que estaba en CC)
            if ($totalPagadoReal >= $total) {
                // Eliminar TODOS los registros de cuenta corriente
                $eliminados = $venta->pagos()->where('metodo_pago_id', $cuentaCorrienteId)->delete();
                
                $venta->load('pagos');
                $venta->save();
                
                return response()->json([
                    'message' => 'Pagos consolidados correctamente',
                    'eliminados' => $eliminados,
                    'total_venta' => $total,
                    'total_pagado' => $totalPagadoReal,
                    'deuda_eliminada' => $totalCuentaCorriente
                ]);
            }
            
            // Si los pagos reales cubren parte de la cuenta corriente
            if ($totalPagadoReal > $saldoSinCC) {
                $montoCancelaDeuda = $totalPagadoReal - $saldoSinCC;
                
                // Eliminar o reducir registros de cuenta corriente
                $pagosCC = $venta->pagos()->where('metodo_pago_id', $cuentaCorrienteId)->orderBy('id')->get();
                $restantePorCancelar = $montoCancelaDeuda;
                $eliminados = 0;
                
                foreach ($pagosCC as $pagoCC) {
                    if ($restantePorCancelar <= 0) break;
                    
                    $montoCC = (float)$pagoCC->monto;
                    if ($montoCC <= $restantePorCancelar) {
                        $restantePorCancelar -= $montoCC;
                        $pagoCC->delete();
                        $eliminados++;
                    } else {
                        $pagoCC->monto = $montoCC - $restantePorCancelar;
                        $pagoCC->save();
                        $restantePorCancelar = 0;
                    }
                }
                
                $venta->load('pagos');
                $venta->save();
                
                return response()->json([
                    'message' => 'Pagos consolidados parcialmente',
                    'eliminados' => $eliminados,
                    'deuda_cancelada' => $montoCancelaDeuda,
                    'deuda_restante' => $totalCuentaCorriente - $montoCancelaDeuda
                ]);
            }
            
            return response()->json([
                'message' => 'No hay suficientes pagos para consolidar',
                'total_venta' => $total,
                'total_pagado' => $totalPagadoReal,
                'deuda_cc' => $totalCuentaCorriente,
                'saldo_pendiente' => $saldoSinCC
            ], 400);
        });
    }

    /**
     * GET /api/v1/cheques/pendientes
     * Obtiene todos los cheques pendientes, con alerta para los próximos a vencer
     */
    public function chequesPendientes(Request $request)
    {
        $diasAlerta = $request->input('dias_alerta', 7); // Por defecto, alertar 7 días antes

        $chequesPendientes = Pago::with(['venta.cliente', 'metodoPago'])
            ->where('estado_cheque', 'pendiente')
            ->orderBy('fecha_cobro', 'asc')
            ->orderBy('fecha_cheque', 'asc')
            ->orderBy('id', 'desc') // Orden secundario por ID para cheques sin fecha
            ->get()
            ->map(function($pago) use ($diasAlerta) {
                $hoy = \Carbon\Carbon::today();
                
                // Usar fecha_cobro para calcular días restantes (o fecha_cheque si no hay fecha_cobro)
                $fechaVencimiento = $pago->fecha_cobro ?? $pago->fecha_cheque;
                
                if ($fechaVencimiento) {
                    $fechaCheque = \Carbon\Carbon::parse($fechaVencimiento);
                    $diasRestantes = $hoy->diffInDays($fechaCheque, false);
                } else {
                    // Sin fecha = considerarlo como "sin vencimiento definido"
                    $diasRestantes = null;
                }

                return [
                    'id' => $pago->id,
                    'venta_id' => $pago->venta_id,
                    'numero_cheque' => $pago->numero_cheque,
                    'monto' => (float)$pago->monto,
                    'fecha_cheque' => $pago->fecha_cheque ? \Carbon\Carbon::parse($pago->fecha_cheque)->format('Y-m-d') : null,
                    'fecha_cobro' => $pago->fecha_cobro ? \Carbon\Carbon::parse($pago->fecha_cobro)->format('Y-m-d') : null,
                    'fecha_pago' => $pago->fecha_pago->format('Y-m-d'),
                    'observaciones' => $pago->observaciones_cheque,
                    'cliente' => [
                        'id' => $pago->venta->cliente->id,
                        'nombre' => $pago->venta->cliente->nombre . ' ' . $pago->venta->cliente->apellido,
                    ],
                    'dias_restantes' => $diasRestantes !== null ? (int)$diasRestantes : null,
                    'vencido' => $diasRestantes !== null ? ($diasRestantes < 0) : false,
                    'proximo_a_vencer' => $diasRestantes !== null ? ($diasRestantes >= 0 && $diasRestantes <= $diasAlerta) : false,
                    'estado_alerta' => $diasRestantes === null ? 'sin_fecha' : ($diasRestantes < 0 ? 'vencido' : ($diasRestantes <= $diasAlerta ? 'alerta' : 'normal')),
                ];
            });

        $resumen = [
            'total' => $chequesPendientes->count(),
            'vencidos' => $chequesPendientes->where('vencido', true)->count(),
            'proximos_a_vencer' => $chequesPendientes->where('proximo_a_vencer', true)->count(),
            'sin_fecha' => $chequesPendientes->where('estado_alerta', 'sin_fecha')->count(),
            'monto_total' => $chequesPendientes->sum('monto'),
        ];

        return response()->json([
            'cheques' => $chequesPendientes->values(),
            'resumen' => $resumen,
        ]);
    }

    /**
     * GET /api/v1/cheques/historial
     * Obtener historial completo de cheques (pendientes, cobrados, rechazados)
     */
    public function chequesHistorial(Request $request)
    {
        $estado = $request->input('estado'); // pendiente, cobrado, rechazado, null=todos

        $query = Pago::with(['venta.cliente', 'metodoPago'])
            ->whereNotNull('estado_cheque'); // Solo pagos que son cheques

        if ($estado) {
            $query->where('estado_cheque', $estado);
        }

        $cheques = $query->orderBy('updated_at', 'desc')
            ->get()
            ->map(function($pago) {
                return [
                    'id' => $pago->id,
                    'venta_id' => $pago->venta_id,
                    'numero_cheque' => $pago->numero_cheque,
                    'monto' => (float)$pago->monto,
                    'fecha_cheque' => $pago->fecha_cheque ? \Carbon\Carbon::parse($pago->fecha_cheque)->format('Y-m-d') : null,
                    'fecha_cobro' => $pago->fecha_cobro ? \Carbon\Carbon::parse($pago->fecha_cobro)->format('Y-m-d') : null,
                    'fecha_pago' => $pago->fecha_pago->format('Y-m-d'),
                    'fecha_procesado' => $pago->updated_at->format('Y-m-d H:i:s'),
                    'estado_cheque' => $pago->estado_cheque,
                    'observaciones' => $pago->observaciones_cheque,
                    'cliente' => [
                        'id' => $pago->venta->cliente->id,
                        'nombre' => $pago->venta->cliente->nombre . ' ' . $pago->venta->cliente->apellido,
                    ],
                ];
            });

        return response()->json([
            'cheques' => $cheques->values(),
            'total' => $cheques->count(),
        ]);
    }

    /**
     * POST /api/v1/pagos/corregir-cheques-historicos
     * Corrige el saldo de clientes que tienen cheques pendientes que fueron contados incorrectamente
     */
    public function corregirChequesHistoricos()
    {
        return \DB::transaction(function () {
            $corregidos = [];
            
            // Buscar todos los cheques pendientes
            $chequesPendientes = Pago::where('estado_cheque', 'pendiente')
                ->with(['venta.cliente'])
                ->get();
            
            foreach ($chequesPendientes as $pago) {
                $venta = $pago->venta;
                $cliente = $venta->cliente;
                $monto = (float)$pago->monto;
                
                // Incrementar el saldo_actual (lógica original)
                $saldoAnterior = $cliente->saldo_actual;
                $cliente->saldo_actual = round((float)$cliente->saldo_actual + $monto, 2);
                $cliente->save();
                
                // Eliminar movimientos de cuenta corriente asociados a este cheque pendiente
                \App\Models\MovimientoCuentaCorriente::where('referencia_id', $pago->id)
                    ->where('tipo', 'pago')
                    ->delete();
                
                $corregidos[] = [
                    'pago_id' => $pago->id,
                    'venta_id' => $venta->id,
                    'cliente' => $cliente->nombre . ' ' . $cliente->apellido,
                    'monto' => $monto,
                    'saldo_anterior' => $saldoAnterior,
                    'saldo_nuevo' => $cliente->saldo_actual,
                ];
                
                \Log::info("Cheque pendiente corregido: Pago #{$pago->id}, Cliente #{$cliente->id}, Monto devuelto: {$monto}");
            }
            
            return response()->json([
                'message' => 'Cheques pendientes corregidos',
                'total_corregidos' => count($corregidos),
                'detalles' => $corregidos,
            ]);
        });
    }
}
