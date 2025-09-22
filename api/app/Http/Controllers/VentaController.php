<?php
namespace App\Http\Controllers;

use App\Http\Requests\VentaStoreRequest;
use App\Http\Resources\VentaResource;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:ventas.index')->only(['index','show']);
        $this->middleware('permission:ventas.store')->only(['store']);
    }

    public function index(Request $request)
    {
        $qCliente = $request->integer('cliente_id');
        $estado   = $request->string('estado_pago');
        $desde    = $request->date('desde');
        $hasta    = $request->date('hasta');

        $query = Venta::with('items')->orderByDesc('fecha');

        if ($qCliente) $query->where('cliente_id', $qCliente);
        if ($estado && $estado->isNotEmpty()) $query->where('estado_pago', $estado);
        if ($desde) $query->whereDate('fecha', '>=', $desde);
        if ($hasta) $query->whereDate('fecha', '<=', $hasta);

        return VentaResource::collection($query->paginate(15));
    }

    public function store(VentaStoreRequest $request, VentaService $service)
    {
        $usuarioId = (int) $request->user()->id;
        $venta = $service->crearVenta($request->validated(), $usuarioId);

        return (new VentaResource($venta))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Venta $venta)
    {
        $venta->load('items');
        return new VentaResource($venta);
    }
}
