<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompraProveedorRequest;
use App\Http\Resources\CompraResource;
use App\Models\Proveedor;
use App\Models\Compra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraProveedorController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:proveedores.compras.index,api')->only(['index']);
        $this->middleware('permission:proveedores.compras.store,api')->only(['store']);
        $this->middleware('permission:proveedores.compras.show,api')->only(['show']);
        $this->middleware('permission:proveedores.compras.destroy,api')->only(['destroy']);
    }

    /**
     * Listar compras de un proveedor
     * 
     * @param Request $request
     * @param Proveedor $proveedor
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, Proveedor $proveedor)
    {
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');
        $estado = $request->input('estado');

        $query = $proveedor->compras()->with('detalles');

        // Filtro por rango de fechas
        if ($fechaDesde) {
            $query->where('fecha_compra', '>=', $fechaDesde);
        }
        if ($fechaHasta) {
            $query->where('fecha_compra', '<=', $fechaHasta);
        }
        
        // Filtro por estado
        if ($estado) {
            $query->where('estado', $estado);
        }

        $compras = $query->orderBy('fecha_compra', 'desc')->get();

        // Calcular resumen
        $resumen = [
            'total_compras' => $compras->count(),
            'monto_total' => $compras->sum('monto_total'),
            'por_estado' => [
                'pendiente' => $compras->where('estado', 'pendiente')->sum('monto_total'),
                'pagado' => $compras->where('estado', 'pagado')->sum('monto_total'),
                'anulado' => $compras->where('estado', 'anulado')->count(),
            ],
        ];

        return CompraResource::collection($compras)
            ->additional([
                'resumen' => $resumen,
            ]);
    }

    /**
     * Registrar una nueva compra a un proveedor
     * 
     * @param StoreCompraProveedorRequest $request
     * @param Proveedor $proveedor
     * @return CompraResource
     */
    public function store(StoreCompraProveedorRequest $request, Proveedor $proveedor)
    {
        $data = $request->validated();
        
        try {
            DB::beginTransaction();
            
            // Crear la compra
            $compra = new Compra();
            $compra->proveedor_id = $proveedor->id;
            $compra->fecha_compra = $data['fecha_compra'];
            $compra->estado = $data['estado'] ?? 'pendiente';
            $compra->metodo_pago = $data['metodo_pago'] ?? null;
            $compra->subtotal = $data['subtotal'];
            $compra->descuento_global = $data['descuento_global'] ?? 0;
            $compra->impuestos_total = $data['impuestos_total'] ?? 0;
            $compra->monto_total = $data['monto_total'];
            $compra->observaciones = $data['observaciones'] ?? null;
            $compra->save();
            
            // Crear los detalles de la compra
            foreach ($data['detalles'] as $detalle) {
                $compra->detalles()->create([
                    'producto_id' => $detalle['producto_id'] ?? null,
                    'descripcion' => $detalle['descripcion'],
                    'unidad_medida' => $detalle['unidad_medida'] ?? null,
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'descuento_item' => $detalle['descuento_item'] ?? 0,
                    'impuesto_porcentaje' => $detalle['impuesto_porcentaje'] ?? 0,
                    'impuesto_monto' => $detalle['impuesto_monto'] ?? 0,
                    'subtotal' => $detalle['subtotal'],
                ]);
            }
            
            DB::commit();
            
            // Cargar relaciones para la respuesta
            $compra->load('detalles', 'proveedor');
            
            return new CompraResource($compra);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar la compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar detalle de una compra específica
     * 
     * @param Compra $compra
     * @return CompraResource
     */
    public function show(Compra $compra)
    {
        $compra->load('detalles', 'proveedor');
        return new CompraResource($compra);
    }

    /**
     * Anular una compra (soft delete)
     * 
     * @param Compra $compra
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Compra $compra)
    {
        try {
            // Cambiar estado a "anulado" en lugar de eliminar
            $compra->estado = 'anulado';
            $compra->save();
            $compra->delete(); // Soft delete
            
            return response()->json([
                'message' => 'Compra anulada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al anular la compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
