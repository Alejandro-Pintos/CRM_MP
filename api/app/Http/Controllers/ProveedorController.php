<?php
namespace App\Http\Controllers;

use App\Http\Requests\ProveedorStoreRequest;
use App\Http\Requests\ProveedorUpdateRequest;
use App\Http\Resources\ProveedorResource;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:proveedores.index')->only(['index','show']);
        $this->middleware('permission:proveedores.store')->only(['store']);
        $this->middleware('permission:proveedores.update')->only(['update']);
        $this->middleware('permission:proveedores.destroy')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $q = $request->string('q');
        $estado = $request->string('estado');
        $perPage = $request->input('per_page', 15);

        $query = Proveedor::query();
        if ($q->isNotEmpty()) {
            $query->where(function($qq) use ($q) {
                $qq->where('nombre','like','%'.$q.'%')
                   ->orWhere('cuit','like','%'.$q.'%');
            });
        }
        if ($estado->isNotEmpty()) {
            $query->where('estado', $estado);
        }

        // Si per_page es 'all', devolver todos sin paginación
        if ($perPage === 'all') {
            return ProveedorResource::collection($query->orderBy('nombre')->get());
        }

        return ProveedorResource::collection($query->orderBy('nombre')->paginate($perPage));
    }

    public function store(ProveedorStoreRequest $request)
    {
        $prov = Proveedor::create($request->validated());
        return (new ProveedorResource($prov))->response()->setStatusCode(201);
    }

    public function show(int $id)
    {
        return new ProveedorResource(Proveedor::findOrFail($id));
    }

    public function update(ProveedorUpdateRequest $request, int $id)
    {
        $prov = Proveedor::findOrFail($id);
        $prov->update($request->validated());
        return new ProveedorResource($prov);
    }

    public function destroy(int $id)
    {
        Proveedor::findOrFail($id)->delete();
        return response()->noContent();
    }
}
