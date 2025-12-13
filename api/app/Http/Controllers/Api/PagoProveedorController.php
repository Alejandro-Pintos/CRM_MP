<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePagoProveedorRequest;
use App\Http\Resources\PagoProveedorResource;
use App\Models\Proveedor;
use App\Models\PagoProveedor;
use Illuminate\Http\Request;

class PagoProveedorController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:proveedores.pagos.index,api')->only(['index']);
        $this->middleware('permission:proveedores.pagos.store,api')->only(['store']);
        $this->middleware('permission:proveedores.pagos.destroy,api')->only(['destroy']);
    }

    /**
     * Listar pagos de un proveedor
     * 
     * @param Request $request
     * @param Proveedor $proveedor
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, Proveedor $proveedor)
    {
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');

        $query = $proveedor->pagos()->with('metodoPago');

        // Filtro por rango de fechas
        if ($fechaDesde) {
            $query->where('fecha_pago', '>=', $fechaDesde);
        }
        if ($fechaHasta) {
            $query->where('fecha_pago', '<=', $fechaHasta);
        }

        $pagos = $query->orderBy('fecha_pago', 'desc')->get();

        // Calcular resumen
        $resumen = [
            'total_pagos' => $pagos->count(),
            'monto_total' => $pagos->sum('monto'),
        ];

        return PagoProveedorResource::collection($pagos)
            ->additional([
                'resumen' => $resumen,
            ]);
    }

    /**
     * Registrar un nuevo pago a un proveedor
     * 
     * @param StorePagoProveedorRequest $request
     * @param Proveedor $proveedor
     * @return PagoProveedorResource
     */
    public function store(StorePagoProveedorRequest $request, Proveedor $proveedor)
    {
        $data = $request->validated();
        $data['proveedor_id'] = $proveedor->id;
        
        // Si hay usuario autenticado, registrarlo
        if (auth()->check()) {
            $data['usuario_id'] = auth()->id();
        }

        $pago = PagoProveedor::create($data);
        
        // Si el método de pago es Cheque (id 4), crear el cheque emitido
        if (isset($data['metodo_pago_id']) && $data['metodo_pago_id'] == 4) {
            $pago->crearChequeEmitido($data);
        }
        
        $pago->load(['metodoPago', 'cheque']);

        return (new PagoProveedorResource($pago))
            ->additional(['message' => 'Pago registrado correctamente'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Eliminar un pago de proveedor
     * 
     * @param PagoProveedor $pago
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(PagoProveedor $pago)
    {
        $pago->delete();
        
        return response()->json([
            'message' => 'Pago eliminado correctamente'
        ], 204);
    }
}
