<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use Illuminate\Http\Request;

class ProductosController extends Controller
class ProductosController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:productos.index')->only(['index','show']);
        $this->middleware('permission:productos.store')->only(['store']);
        $this->middleware('permission:productos.update')->only(['update']);
        $this->middleware('permission:productos.destroy')->only(['destroy']);
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
        $q = $request->get('q');
        $productos = Producto::when($q, fn($query) =>
            $query->where('nombre', 'like', "%$q%")
                  ->orWhere('descripcion', 'like', "%$q%")

        $productos = Producto::when($q, fn($query) =>
            $query->where('nombre', 'like', "%$q%")
                  ->orWhere('descripcion', 'like', "%$q%")
        )->paginate(10);

        return ProductoResource::collection($productos);
        return ProductoResource::collection($productos);
    }

    public function store(StoreProductoRequest $request)
    /**
     * Crear un nuevo producto.
     */
    public function store(StoreProductoRequest $request)
    {
        $producto = Producto::create($request->validated());

        return (new ProductoResource($producto))
            ->additional(['message' => 'Producto creado']);
        $producto = Producto::create($request->validated());

        return (new ProductoResource($producto))
            ->additional(['message' => 'Producto creado']);
    }

    public function show(Producto $producto)
    /**
     * Mostrar un producto específico.
     */
    public function show(Producto $producto)
    {
        return new ProductoResource($producto);
    }

    public function update(UpdateProductoRequest $request, Producto $producto)
        return new ProductoResource($producto);
    }

    /**
     * Actualizar un producto existente.
     */
    public function update(UpdateProductoRequest $request, Producto $producto)
    {
        $producto->update($request->validated());

        return (new ProductoResource($producto))
            ->additional(['message' => 'Producto actualizado']);
        $producto->update($request->validated());

        return (new ProductoResource($producto))
            ->additional(['message' => 'Producto actualizado']);
    }

    public function destroy(Producto $producto)
    /**
     * Eliminar un producto.
     */
    public function destroy(Producto $producto)
    {
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado']);
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado']);
    }
}
