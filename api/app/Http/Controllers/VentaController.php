<?php
namespace App\Http\Controllers;

use App\Http\Requests\VentaStoreRequest;
use App\Http\Resources\VentaResource;
use App\Models\Venta;
use App\Models\Cliente;
use App\Models\ComprobanteNumeracion;
use App\Services\VentaService;
use App\Services\Ventas\RegistrarVentaService;
use App\Services\Ventas\ResumenPagosVentaService;
use App\Services\Finanzas\CuentaCorrienteService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        $query = Venta::with([
            'items.producto',       // Evita N+1 al mostrar nombres de productos
            'cliente',
            'pagos.metodoPago',     // Evita N+1 al mostrar métodos de pago
            'cheques',              // Incluye cheques asociados
        ])->orderByDesc('fecha');

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

/**
     * Crear nueva venta usando RegistrarVentaService
     * 
     * El servicio se encarga de:
     * - Calcular total desde items (backend)
     * - Validar límite de crédito
     * - Crear venta + items + pagos
     * - Registrar cheques si corresponde
     * - Registrar deuda en CC si hay saldo pendiente
     * - Determinar estado_pago automáticamente
     */
    public function store(VentaStoreRequest $request, RegistrarVentaService $registrarVentaService)
    {
        try {
            $validated = $request->validated();
            
            // Obtener cliente
            $cliente = Cliente::findOrFail($validated['cliente_id']);
            
            // Ejecutar servicio de dominio
            $venta = $registrarVentaService->ejecutar($cliente, $validated);
            
            // Retornar recurso con código 201
            return (new VentaResource($venta))
                ->response()
                ->setStatusCode(201)
                ->header('Location', route('ventas.show', ['venta' => $venta->id]));
                
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error al crear venta: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error al crear la venta: ' . $e->getMessage()
            ], 500);
        }
    }


    public function show(Venta $venta)
    {
        $venta->load([
            'items.producto',
            'pagos.metodoPago',
            'cheques',
            'cliente',
        ]);
        return new VentaResource($venta);
    }

    /**
     * Obtener resumen de pagos de una venta
     * 
     * Calcula:
     * - Total Venta
     * - Total Cobrado (efectivo, transferencia, etc.)
     * - Total Cheques (independiente de estado)
     * - Total Deuda C.C.
     * - Saldo Pendiente
     */
    public function resumenPagos(Venta $venta, ResumenPagosVentaService $resumenService)
    {
        return response()->json($resumenService->calcular($venta));
    }

    /**
     * Eliminar una venta.
     * Cancela la deuda en cuenta corriente y elimina registros asociados.
     */
    public function destroy(Venta $venta, CuentaCorrienteService $cuentaCorrienteService)
    {
        // Validar autorización con policy
        $this->authorize('delete', $venta);

        try {
            \DB::beginTransaction();

            // 1. Cancelar deuda en cuenta corriente usando servicio centralizado
            // Esto crea automáticamente el movimiento de reversión y actualiza saldo
            $cuentaCorrienteService->cancelarDeudaPorVenta($venta);

            // 2. Eliminar pagos asociados
            $venta->pagos()->delete();

            // 3. Eliminar items de venta
            $venta->items()->delete();
            
            // 4. Eliminar la venta
            $venta->delete();

            \DB::commit();

            \Log::info("Venta #{$venta->id} eliminada correctamente");

            return response()->json([
                'message' => 'Venta eliminada correctamente'
            ], 200);

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error al eliminar venta: ' . $e->getMessage(), [
                'venta_id' => $venta->id,
                'trace' => $e->getTraceAsString()
            ]);
            
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
