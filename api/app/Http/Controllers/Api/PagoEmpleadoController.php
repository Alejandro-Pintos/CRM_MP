<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePagoEmpleadoRequest;
use App\Http\Resources\PagoEmpleadoResource;
use App\Models\Empleado;
use App\Models\PagoEmpleado;
use Illuminate\Http\Request;

class PagoEmpleadoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:empleados.pagos.index')->only(['index']);
        $this->middleware('permission:empleados.pagos.store')->only(['store']);
        $this->middleware('permission:empleados.pagos.destroy')->only(['destroy']);
    }

    /**
     * Listar pagos de un empleado
     * 
     * @param Request $request
     * @param Empleado $empleado
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request, Empleado $empleado)
    {
        $fechaDesde = $request->input('fecha_desde');
        $fechaHasta = $request->input('fecha_hasta');

        $query = $empleado->pagos()->with('metodoPago');

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

        return PagoEmpleadoResource::collection($pagos)
            ->additional([
                'resumen' => $resumen,
            ]);
    }

    /**
     * Registrar un nuevo pago a un empleado
     * 
     * @param StorePagoEmpleadoRequest $request
     * @param Empleado $empleado
     * @return PagoEmpleadoResource
     */
    public function store(StorePagoEmpleadoRequest $request, Empleado $empleado)
    {
        $data = $request->validated();
        $data['empleado_id'] = $empleado->id;

        $pago = PagoEmpleado::create($data);
        $pago->load('metodoPago');

        return (new PagoEmpleadoResource($pago))
            ->additional(['message' => 'Pago registrado correctamente'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Eliminar un pago de empleado
     * 
     * @param PagoEmpleado $pago
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(PagoEmpleado $pago)
    {
        $pago->delete();
        
        return response()->json([
            'message' => 'Pago eliminado correctamente'
        ], 204);
    }
}
