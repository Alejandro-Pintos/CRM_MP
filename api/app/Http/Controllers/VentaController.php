<?php
namespace App\Http\Controllers;

use App\Http\Requests\VentaStoreRequest;
use App\Http\Resources\VentaResource;
use App\Models\Venta;
use App\Models\ComprobanteNumeracion;
use App\Services\VentaService;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:ventas.index')->only(['index','show']);
        $this->middleware('permission:ventas.store')->only(['store']);
        $this->middleware('permission:ventas.destroy')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $qCliente = $request->integer('cliente_id');
        $estado   = $request->string('estado_pago');
        $desde    = $request->date('desde');
        $hasta    = $request->date('hasta');
        $perPage  = $request->input('per_page', 15);

        $query = Venta::with(['items', 'cliente', 'pagos'])->orderByDesc('fecha');

        if ($qCliente) $query->where('cliente_id', $qCliente);
        if ($estado && $estado->isNotEmpty()) $query->where('estado_pago', $estado);
        if ($desde) $query->whereDate('fecha', '>=', $desde);
        if ($hasta) $query->whereDate('fecha', '<=', $hasta);

        // Si per_page es 'all', devolver todos sin paginación
        if ($perPage === 'all') {
            return VentaResource::collection($query->get());
        }

        return VentaResource::collection($query->paginate($perPage));
    }

public function store(VentaStoreRequest $request, VentaService $service)
{
    $usuarioId = (int) optional($request->user())->id;

    // 1) Crear la venta con el servicio
    $venta = $service->crearVenta($request->validated(), $usuarioId);

    // 2) Asegurarnos de tener una instancia de App\Models\Venta
    if (is_array($venta)) {
        // por si el service devuelve ['venta' => $venta, 'items' => [...]] o similar
        $venta = $venta['venta'] ?? $venta[0] ?? null;
    }
    if (! $venta instanceof \App\Models\Venta) {
        abort(500, 'VentaService debe devolver una instancia de App\\Models\\Venta.');
    }

    // 3) Cargar relaciones para que el Resource tenga todo listo (evita N+1)
    $venta->loadMissing(['items', 'cliente', 'pagos']);

    // 4) Devolver 201 + Location con el Resource
    return (new \App\Http\Resources\VentaResource($venta))
        ->response()
        ->setStatusCode(201)
        ->header('Location', route('ventas.show', ['venta' => $venta->id]));
}


    public function show(Venta $venta)
    {
        $venta->load(['items', 'pagos']);
        return new VentaResource($venta);
    }

    /**
     * Eliminar una venta.
     * Elimina en cascada: pagos, items, movimientos de cuenta corriente y ajusta saldo del cliente.
     */
    public function destroy(Venta $venta)
    {
        try {
            \DB::beginTransaction();

            // 1. Obtener información antes de eliminar
            $cliente = $venta->cliente;
            
            // 2. Calcular el monto en cuenta corriente ANTES de eliminar los pagos
            $montoCuentaCorriente = 0;
            if ($cliente) {
                $montoCuentaCorriente = $venta->pagos()
                    ->whereHas('metodoPago', function($q) {
                        $q->where('nombre', 'Cuenta Corriente');
                    })
                    ->sum('monto');
            }

            // 3. Ajustar saldo del cliente si hay monto en cuenta corriente
            if ($montoCuentaCorriente > 0 && $cliente) {
                // Restar el monto que estaba en cuenta corriente (lógica original)
                $cliente->saldo_actual = (float)$cliente->saldo_actual - $montoCuentaCorriente;
                $cliente->save();
                
                \Log::info("Venta #{$venta->id} eliminada. Cliente #{$cliente->id}: Saldo ajustado de " . 
                          ($cliente->saldo_actual + $montoCuentaCorriente) . " a {$cliente->saldo_actual}");
                
                // Crear movimiento de reversión/cancelación para auditoría
                \App\Models\MovimientoCuentaCorriente::create([
                    'cliente_id' => $cliente->id,
                    'tipo' => 'pago',
                    'referencia_id' => $venta->id,
                    'monto' => $montoCuentaCorriente,
                    'debe' => 0,
                    'haber' => abs($montoCuentaCorriente),  // Reversión = HABER (reduce deuda)
                    'fecha' => now(),
                    'descripcion' => "Cancelación de venta #{$venta->id}" . 
                                   ($venta->numero_comprobante ? " - {$venta->tipo_comprobante} {$venta->numero_comprobante}" : ''),
                ]);
            }

            // 4. NO eliminar movimientos de cuenta corriente - mantener historial de auditoría
            // Los movimientos antiguos se mantienen, y se agregó uno nuevo de reversión arriba

            // 5. Eliminar pagos asociados (incluyendo cheques)
            $venta->pagos()->delete();

            // 6. Eliminar items de venta
            $venta->items()->delete();
            
            // 7. Eliminar la venta (soft delete)
            $venta->delete();

            \DB::commit();

            return response()->json([
                'message' => 'Venta eliminada correctamente'
            ], 200);

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error al eliminar venta: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar la venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Previsualizar el próximo número de comprobante.
     */
    public function previsualizarNumero(Request $request)
    {
        $tipoComprobante = $request->input('tipo_comprobante');
        $puntoVenta = $request->input('punto_venta', '0001');

        if (!$tipoComprobante) {
            return response()->json(['error' => 'Tipo de comprobante requerido'], 400);
        }

        $numero = ComprobanteNumeracion::previsualizarNumero($tipoComprobante, $puntoVenta);

        return response()->json([
            'tipo_comprobante' => $tipoComprobante,
            'punto_venta' => $puntoVenta,
            'numero' => $numero,
        ]);
    }
}
