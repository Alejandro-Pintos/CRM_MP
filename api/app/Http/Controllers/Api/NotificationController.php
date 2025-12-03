<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Services\SystemAlertsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controlador para gestionar notificaciones/alertas del sistema
 */
class NotificationController extends Controller
{
    protected SystemAlertsService $alertsService;

    public function __construct(SystemAlertsService $alertsService)
    {
        $this->middleware(['auth:api']);
        
        // Permisos: cualquier usuario autenticado puede ver sus alertas
        // Si necesitas restringir más, puedes usar:
        // $this->middleware('permission:notificaciones.ver')->only(['index', 'resumen']);
        
        $this->alertsService = $alertsService;
    }

    /**
     * Obtiene resumen de contadores de alertas
     * 
     * GET /api/v1/notificaciones/resumen
     * 
     * @return JsonResponse
     */
    public function resumen(): JsonResponse
    {
        $summary = $this->alertsService->getSummary();

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Obtiene listado paginado de alertas con filtros
     * 
     * GET /api/v1/notificaciones
     * 
     * Query params disponibles:
     * - tipo: cheques_proximos_vencer | cheques_vencidos | pedidos_proximos_entregar | pedidos_atrasados
     * - nivel: info | warning | critical
     * - fecha_desde: YYYY-MM-DD
     * - fecha_hasta: YYYY-MM-DD
     * - per_page: int (default: 15)
     * 
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['tipo', 'nivel', 'fecha_desde', 'fecha_hasta']);
        $perPage = $request->input('per_page', config('alerts.general.paginacion_default', 15));

        // Validar per_page
        if (!is_numeric($perPage) || $perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        $alerts = $this->alertsService->getAlerts($filters, (int) $perPage);

        return AlertResource::collection($alerts);
    }

    /**
     * Limpia cache de alertas (útil para testing o cuando se modifica un cheque/pedido)
     * 
     * POST /api/v1/notificaciones/limpiar-cache
     * 
     * @return JsonResponse
     */
    public function limpiarCache(): JsonResponse
    {
        \Illuminate\Support\Facades\Cache::forget('alerts_summary');

        return response()->json([
            'success' => true,
            'message' => 'Cache de alertas limpiado correctamente',
        ]);
    }
}
