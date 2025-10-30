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
        $perPage  = $request->input('per_page', 15);

        $query = Venta::with(['items', 'cliente'])->orderByDesc('fecha');

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
        $venta->load('items');
        return new VentaResource($venta);
    }
}
