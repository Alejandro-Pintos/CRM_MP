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

        $pago->estado_cheque = $request->estado_cheque;
        $pago->fecha_cobro = $request->fecha_cobro ?? now();
        if ($request->has('observaciones_cheque')) {
            $pago->observaciones_cheque = $request->observaciones_cheque;
        }
        $pago->save();

        // Recargar la venta para actualizar el estado
        $venta = $pago->venta;
        $venta->load('pagos');
        $venta->save(); // Esto dispara el accessor estadoPago

        return new PagoResource($pago->load('metodoPago'));
    }
}
