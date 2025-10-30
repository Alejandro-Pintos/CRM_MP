<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use Illuminate\Http\Request;

class ProductosController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:productos.index')->only(['index', 'show']);
        $this->middleware('permission:productos.store')->only(['store']);
        $this->middleware('permission:productos.update')->only(['update']);
        $this->middleware('permission:productos.destroy')->only(['destroy']);
    }

    /**
     * Listar productos con búsqueda opcional.
     */
    public function index(Request $request)
    {
        $q = $request->string('q');
        $perPage = $request->input('per_page', 10);

        $query = Producto::query();

        if ($q->isNotEmpty()) {
            $query->where(function ($qq) use ($q) {
                $qq->where('nombre', 'like', "%{$q}%")
                   ->orWhere('descripcion', 'like', "%{$q}%")
                   ->orWhere('codigo', 'like', "%{$q}%");
            });
        }

        // Si per_page es 'all', devolver todos sin paginación
        if ($perPage === 'all') {
            return ProductoResource::collection(
                $query->orderBy('nombre')->get()
            );
        }

        return ProductoResource::collection(
            $query->orderBy('nombre')->paginate($perPage)
        );
    }

    /**
     * Crear un nuevo producto.
     * Devuelve 201 Created + Location header al recurso creado.
     */
    public function store(StoreProductoRequest $request)
    {
        $producto = Producto::create($request->validated());

        return (new ProductoResource($producto))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('productos.show', $producto));
    }

    /**
     * Mostrar un producto específico.
     */
    public function show(Producto $producto)
    {
        return new ProductoResource($producto);
    }

    /**
     * Actualizar un producto existente.
     */
    public function update(UpdateProductoRequest $request, Producto $producto)
    {
        $producto->update($request->validated());

        return new ProductoResource($producto); // 200 OK por defecto
    }

    /**
     * Eliminar un producto (soft delete).
     * Devuelve 204 No Content.
     */
    public function destroy(Producto $producto)
    {
        $producto->delete();
        return response()->noContent(); // 204
    }
}
