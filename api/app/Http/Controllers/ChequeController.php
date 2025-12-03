<?php

namespace App\Http\Controllers;

use App\Models\Cheque;
use App\Http\Resources\ChequeResource;
use App\Services\Finanzas\ChequeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChequeController extends Controller
{
    protected $chequeService;

    public function __construct(ChequeService $chequeService)
    {
        $this->middleware(['auth:api']);
        $this->chequeService = $chequeService;
    }

    /**
     * GET /api/v1/cheques
     * Listar cheques con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $estado = $request->input('estado', 'pendiente'); // pendiente|cobrado|rechazado|todos
        $diasAlerta = $request->input('dias_alerta', 7);
        
        $query = Cheque::with(['cliente', 'venta']);

        // Filtrar por estado
        if ($estado !== 'todos') {
            $query->where('estado', $estado);
        }

        // Si es pendiente, calcular alertas
        if ($estado === 'pendiente') {
            $cheques = $this->chequeService->obtenerChequesPendientesConAlertas($diasAlerta);
            
            // Calcular resumen
            $resumen = [
                'total' => $cheques->count(),
                'vencidos' => $cheques->where('vencido', true)->count(),
                'proximos_a_vencer' => $cheques->where('proximo_a_vencer', true)->count(),
                'sin_fecha' => $cheques->filter(fn($c) => is_null($c->fecha_vencimiento))->count(),
                'monto_total' => $cheques->sum('monto'),
            ];

            return response()->json([
                'cheques' => ChequeResource::collection($cheques),
                'resumen' => $resumen,
            ]);
        }

        // Para otros estados, devolver lista simple
        $cheques = $query->get();
        
        return response()->json([
            'cheques' => ChequeResource::collection($cheques),
            'total' => $cheques->count(),
        ]);
    }

    /**
     * GET /api/v1/cheques/{cheque}
     * Ver detalle de un cheque.
     */
    public function show(Cheque $cheque): ChequeResource
    {
        $cheque->load(['cliente', 'venta', 'pago']);
        return new ChequeResource($cheque);
    }

    /**
     * PATCH /api/v1/cheques/{cheque}
     * Actualizar datos administrativos de un cheque pendiente.
     */
    public function update(Request $request, Cheque $cheque): ChequeResource
    {
        $data = $request->validate([
            'numero' => 'nullable|string|max:50',
            'fecha_emision' => 'nullable|date',
            'fecha_vencimiento' => 'nullable|date',
            'observaciones' => 'nullable|string',
        ]);

        $cheque = $this->chequeService->actualizarDatos($cheque, $data);

        return new ChequeResource($cheque->fresh(['cliente', 'venta']));
    }

    /**
     * POST /api/v1/cheques/{cheque}/cobrar
     * Marcar cheque como cobrado.
     * 
     * IMPORTANTE: Cuando un cheque se cobra, el resumen de pagos de la venta debe actualizarse.
     */
    public function cobrar(Request $request, Cheque $cheque): ChequeResource
    {
        $data = $request->validate([
            'fecha_cobro' => 'nullable|date',
        ]);

        $cheque = $this->chequeService->marcarComoCobrado(
            $cheque,
            $data['fecha_cobro'] ?? null
        );

        // Disparar evento para que el frontend recargue el resumen de pagos
        // (Cache busting implícito)
        \Log::info('Cheque cobrado - frontend debe recargar resumen', [
            'cheque_id' => $cheque->id,
            'venta_id' => $cheque->venta_id,
        ]);

        return new ChequeResource($cheque->fresh(['cliente', 'venta']));
    }

    /**
     * POST /api/v1/cheques/{cheque}/rechazar
     * Marcar cheque como rechazado.
     * 
     * IMPORTANTE: Cuando un cheque se rechaza, el resumen de pagos de la venta debe actualizarse.
     */
    public function rechazar(Request $request, Cheque $cheque): ChequeResource
    {
        $data = $request->validate([
            'motivo_rechazo' => 'nullable|string',
        ]);

        $cheque = $this->chequeService->marcarComoRechazado(
            $cheque,
            $data['motivo_rechazo'] ?? null
        );

        // Disparar evento para que el frontend recargue el resumen de pagos
        \Log::info('Cheque rechazado - frontend debe recargar resumen', [
            'cheque_id' => $cheque->id,
            'venta_id' => $cheque->venta_id,
        ]);

        return new ChequeResource($cheque->fresh(['cliente', 'venta']));
    }

    /**
     * GET /api/v1/cheques/pendientes
     * Listar cheques pendientes con alertas de vencimiento.
     */
    public function pendientes(Request $request): JsonResponse
    {
        $diasAlerta = $request->input('dias_alerta', 7);
        
        // Usar el servicio para obtener cheques pendientes con alertas
        $cheques = $this->chequeService->obtenerChequesPendientesConAlertas($diasAlerta);
        
        // Calcular resumen
        $resumen = [
            'total' => $cheques->count(),
            'vencidos' => $cheques->where('vencido', true)->count(),
            'proximos_a_vencer' => $cheques->where('proximo_a_vencer', true)->count(),
            'sin_fecha' => $cheques->filter(fn($c) => is_null($c->fecha_vencimiento))->count(),
            'monto_total' => $cheques->sum('monto'),
        ];

        return response()->json([
            'cheques' => ChequeResource::collection($cheques),
            'resumen' => $resumen,
        ]);
    }

    /**
     * GET /api/v1/cheques/historial
     * Historial completo de cheques (cobrados/rechazados).
     */
    public function historial(Request $request): JsonResponse
    {
        $estado = $request->input('estado'); // cobrado|rechazado|null=todos

        $query = Cheque::with(['cliente', 'venta'])
            ->whereIn('estado', ['cobrado', 'rechazado']);

        if ($estado) {
            $query->where('estado', $estado);
        }

        $cheques = $query->orderByDesc('updated_at')->get();

        return response()->json([
            'cheques' => ChequeResource::collection($cheques),
            'total' => $cheques->count(),
        ]);
    }
}
