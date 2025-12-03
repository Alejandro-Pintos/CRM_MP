<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use App\Services\ProveedorEstadoCuentaService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProveedorEstadoCuentaController extends Controller
{
    protected $estadoCuentaService;

    public function __construct(ProveedorEstadoCuentaService $estadoCuentaService)
    {
        $this->middleware(['auth:api']);
        $this->middleware('permission:proveedores.cuenta.index')->only(['resumen', 'movimientos']);
        
        $this->estadoCuentaService = $estadoCuentaService;
    }

    /**
     * Obtener resumen de estado de cuenta de un proveedor
     * 
     * @param Proveedor $proveedor
     * @return \Illuminate\Http\JsonResponse
     */
    public function resumen(Proveedor $proveedor)
    {
        $resumen = $this->estadoCuentaService->getResumen($proveedor->id);
        
        return response()->json([
            'data' => $resumen
        ]);
    }

    /**
     * Obtener movimientos de cuenta corriente de un proveedor
     * 
     * @param Request $request
     * @param Proveedor $proveedor
     * @return \Illuminate\Http\JsonResponse
     */
    public function movimientos(Request $request, Proveedor $proveedor)
    {
        $desde = $request->input('from') ? Carbon::parse($request->input('from')) : null;
        $hasta = $request->input('to') ? Carbon::parse($request->input('to')) : null;
        
        $movimientos = $this->estadoCuentaService->getMovimientos(
            $proveedor->id,
            $desde,
            $hasta
        );
        
        // Calcular resumen de estos movimientos
        $resumen = [
            'total_debitos' => $movimientos->sum('debito'),
            'total_creditos' => $movimientos->sum('credito'),
            'saldo_periodo' => $movimientos->sum('debito') - $movimientos->sum('credito'),
            'cantidad_movimientos' => $movimientos->count(),
        ];
        
        return response()->json([
            'data' => $movimientos->values(),
            'resumen' => $resumen,
        ]);
    }
}
