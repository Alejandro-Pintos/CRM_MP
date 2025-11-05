<?php
namespace App\Http\Controllers;

use App\Http\Requests\PagoStoreRequest;
use App\Http\Resources\PagoResource;
use App\Models\Venta;
use App\Services\PagoService;

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
}
