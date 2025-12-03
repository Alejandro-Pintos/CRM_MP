<?php

namespace App\Services;

use App\Models\Cheque;
use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;

/**
 * Servicio centralizado para gestionar alertas del sistema
 * 
 * Este servicio NO persiste notificaciones en base de datos,
 * sino que calcula alertas dinámicamente usando los datos
 * existentes en los modelos Cheque y Pedido.
 */
class SystemAlertsService
{
    /**
     * Obtiene un resumen de contadores de alertas para el usuario actual
     * 
     * @return array
     */
    public function getSummary(): array
    {
        $cacheKey = 'alerts_summary';
        $cacheDuration = config('alerts.general.cache_resumen_minutos', 5);

        return Cache::remember($cacheKey, now()->addMinutes($cacheDuration), function () {
            return [
                'cheques_proximos_vencer' => $this->getChequesCriticalCount(),
                'cheques_vencidos' => $this->getChequesVencidosCount(),
                'pedidos_proximos_entregar' => $this->getPedidosProximosCount(),
                'pedidos_atrasados' => $this->getPedidosAtrasadosCount(),
            ];
        });
    }

    /**
     * Obtiene listado detallado de alertas con filtros y paginación
     * 
     * @param array $filters ['tipo' => string, 'nivel' => string, 'fecha_desde' => date, 'fecha_hasta' => date]
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAlerts(array $filters = [], int $perPage = null)
    {
        $perPage = $perPage ?? config('alerts.general.paginacion_default', 15);
        
        $alerts = collect();

        $tipo = $filters['tipo'] ?? null;

        // Si no se especifica tipo, o es 'cheques_proximos_vencer', incluir estos cheques
        if (!$tipo || $tipo === 'cheques_proximos_vencer') {
            $chequesProximos = $this->getChequesProximosVencer();
            $alerts = $alerts->concat($this->transformChequesToAlerts($chequesProximos, 'cheques_proximos_vencer'));
        }

        // Cheques vencidos
        if (!$tipo || $tipo === 'cheques_vencidos') {
            $chequesVencidos = $this->getChequesVencidos();
            $alerts = $alerts->concat($this->transformChequesToAlerts($chequesVencidos, 'cheques_vencidos'));
        }

        // Pedidos próximos a entregar
        if (!$tipo || $tipo === 'pedidos_proximos_entregar') {
            $pedidosProximos = $this->getPedidosProximosEntregar();
            $alerts = $alerts->concat($this->transformPedidosToAlerts($pedidosProximos, 'pedidos_proximos_entregar'));
        }

        // Pedidos atrasados
        if (!$tipo || $tipo === 'pedidos_atrasados') {
            $pedidosAtrasados = $this->getPedidosAtrasados();
            $alerts = $alerts->concat($this->transformPedidosToAlerts($pedidosAtrasados, 'pedidos_atrasados'));
        }

        // Filtrar por nivel si se especifica
        if (isset($filters['nivel'])) {
            $alerts = $alerts->filter(fn($alert) => $alert['nivel'] === $filters['nivel']);
        }

        // Filtrar por rango de fechas si se especifica
        if (isset($filters['fecha_desde'])) {
            $fechaDesde = Carbon::parse($filters['fecha_desde']);
            $alerts = $alerts->filter(fn($alert) => Carbon::parse($alert['fecha_referencia'])->gte($fechaDesde));
        }

        if (isset($filters['fecha_hasta'])) {
            $fechaHasta = Carbon::parse($filters['fecha_hasta']);
            $alerts = $alerts->filter(fn($alert) => Carbon::parse($alert['fecha_referencia'])->lte($fechaHasta));
        }

        // Ordenar por fecha de referencia (más próximas primero)
        $alerts = $alerts->sortBy('fecha_referencia')->values();

        // Paginar manualmente
        return $this->paginateCollection($alerts, $perPage);
    }

    /**
     * Obtiene conteo de cheques próximos a vencer
     */
    protected function getChequesCriticalCount(): int
    {
        $dias = config('alerts.cheques.dias_preaviso_vencimiento', 7);
        
        return Cheque::proximosAVencer($dias)->count();
    }

    /**
     * Obtiene conteo de cheques vencidos
     */
    protected function getChequesVencidosCount(): int
    {
        return Cheque::vencidos()->count();
    }

    /**
     * Obtiene conteo de pedidos próximos a entregar
     */
    protected function getPedidosProximosCount(): int
    {
        $dias = config('alerts.pedidos.dias_preaviso_entrega', 3);
        $estadosAlertables = config('alerts.pedidos.estados_alertables', ['pendiente', 'en_proceso']);

        return Pedido::whereIn('estado', $estadosAlertables)
            ->whereNotNull('fecha_entrega_aprox')
            ->whereBetween('fecha_entrega_aprox', [
                now(),
                now()->addDays($dias)
            ])
            ->count();
    }

    /**
     * Obtiene conteo de pedidos atrasados
     */
    protected function getPedidosAtrasadosCount(): int
    {
        $estadosAlertables = config('alerts.pedidos.estados_alertables', ['pendiente', 'en_proceso']);

        return Pedido::whereIn('estado', $estadosAlertables)
            ->whereNotNull('fecha_entrega_aprox')
            ->whereDate('fecha_entrega_aprox', '<', now())
            ->count();
    }

    /**
     * Obtiene colección de cheques próximos a vencer
     */
    protected function getChequesProximosVencer(): Collection
    {
        $dias = config('alerts.cheques.dias_preaviso_vencimiento', 7);
        $limite = config('alerts.general.limite_por_tipo', 100);

        return Cheque::with(['cliente', 'venta'])
            ->proximosAVencer($dias)
            ->orderBy('fecha_vencimiento', 'asc')
            ->limit($limite)
            ->get();
    }

    /**
     * Obtiene colección de cheques vencidos
     */
    protected function getChequesVencidos(): Collection
    {
        $limite = config('alerts.general.limite_por_tipo', 100);

        return Cheque::with(['cliente', 'venta'])
            ->vencidos()
            ->orderBy('fecha_vencimiento', 'asc')
            ->limit($limite)
            ->get();
    }

    /**
     * Obtiene colección de pedidos próximos a entregar
     */
    protected function getPedidosProximosEntregar(): Collection
    {
        $dias = config('alerts.pedidos.dias_preaviso_entrega', 3);
        $estadosAlertables = config('alerts.pedidos.estados_alertables', ['pendiente', 'en_proceso']);
        $limite = config('alerts.general.limite_por_tipo', 100);

        return Pedido::with(['cliente', 'venta'])
            ->whereIn('estado', $estadosAlertables)
            ->whereNotNull('fecha_entrega_aprox')
            ->whereBetween('fecha_entrega_aprox', [
                now(),
                now()->addDays($dias)
            ])
            ->orderBy('fecha_entrega_aprox', 'asc')
            ->limit($limite)
            ->get();
    }

    /**
     * Obtiene colección de pedidos atrasados
     */
    protected function getPedidosAtrasados(): Collection
    {
        $estadosAlertables = config('alerts.pedidos.estados_alertables', ['pendiente', 'en_proceso']);
        $limite = config('alerts.general.limite_por_tipo', 100);

        return Pedido::with(['cliente', 'venta'])
            ->whereIn('estado', $estadosAlertables)
            ->whereNotNull('fecha_entrega_aprox')
            ->whereDate('fecha_entrega_aprox', '<', now())
            ->orderBy('fecha_entrega_aprox', 'asc')
            ->limit($limite)
            ->get();
    }

    /**
     * Transforma cheques a estructura de alerta
     */
    protected function transformChequesToAlerts(Collection $cheques, string $tipo): Collection
    {
        return $cheques->map(function ($cheque) use ($tipo) {
            $diasRestantes = $cheque->fecha_vencimiento 
                ? Carbon::parse($cheque->fecha_vencimiento)->diffInDays(now(), false)
                : null;

            return [
                'id' => "cheque_{$cheque->id}",
                'tipo' => $tipo,
                'entidad' => 'cheque',
                'entidad_id' => $cheque->id,
                'mensaje' => $this->generateChequeMessage($cheque, $tipo, $diasRestantes),
                'nivel' => $this->calculateChequeLevel($diasRestantes, $tipo),
                'fecha_referencia' => $cheque->fecha_vencimiento?->format('Y-m-d'),
                'dias_restantes' => $diasRestantes !== null ? abs($diasRestantes) : null,
                'monto' => $cheque->monto,
                'cliente' => [
                    'id' => $cheque->cliente_id,
                    'nombre' => $cheque->cliente?->nombre ?? 'Sin cliente',
                ],
                'venta_id' => $cheque->venta_id,
                'numero_cheque' => $cheque->numero,
            ];
        });
    }

    /**
     * Transforma pedidos a estructura de alerta
     */
    protected function transformPedidosToAlerts(Collection $pedidos, string $tipo): Collection
    {
        return $pedidos->map(function ($pedido) use ($tipo) {
            $diasRestantes = $pedido->fecha_entrega_aprox 
                ? Carbon::parse($pedido->fecha_entrega_aprox)->diffInDays(now(), false)
                : null;

            return [
                'id' => "pedido_{$pedido->id}",
                'tipo' => $tipo,
                'entidad' => 'pedido',
                'entidad_id' => $pedido->id,
                'mensaje' => $this->generatePedidoMessage($pedido, $tipo, $diasRestantes),
                'nivel' => $this->calculatePedidoLevel($diasRestantes, $tipo),
                'fecha_referencia' => $pedido->fecha_entrega_aprox?->format('Y-m-d'),
                'dias_restantes' => $diasRestantes !== null ? abs($diasRestantes) : null,
                'cliente' => [
                    'id' => $pedido->cliente_id,
                    'nombre' => $pedido->cliente?->nombre ?? 'Sin cliente',
                ],
                'venta_id' => $pedido->venta_id,
                'estado' => $pedido->estado,
                'ciudad_entrega' => $pedido->ciudad_entrega,
            ];
        });
    }

    /**
     * Genera mensaje descriptivo para alertas de cheques
     */
    protected function generateChequeMessage(Cheque $cheque, string $tipo, ?int $diasRestantes): string
    {
        $clienteNombre = $cheque->cliente?->nombre ?? 'Cliente desconocido';
        $numero = $cheque->numero ?? 'sin número';
        $monto = '$' . number_format($cheque->monto, 2);

        if ($tipo === 'cheques_vencidos') {
            $diasVencido = abs($diasRestantes);
            return "Cheque {$numero} de {$clienteNombre} ({$monto}) vencido hace {$diasVencido} día(s)";
        }

        // cheques_proximos_vencer
        $dias = abs($diasRestantes);
        if ($dias == 0) {
            return "Cheque {$numero} de {$clienteNombre} ({$monto}) vence HOY";
        }
        return "Cheque {$numero} de {$clienteNombre} ({$monto}) vence en {$dias} día(s)";
    }

    /**
     * Genera mensaje descriptivo para alertas de pedidos
     */
    protected function generatePedidoMessage(Pedido $pedido, string $tipo, ?int $diasRestantes): string
    {
        $clienteNombre = $pedido->cliente?->nombre ?? 'Cliente desconocido';
        $ciudad = $pedido->ciudad_entrega ?? 'ciudad no especificada';

        if ($tipo === 'pedidos_atrasados') {
            $diasAtrasado = abs($diasRestantes);
            return "Pedido #{$pedido->id} para {$clienteNombre} ({$ciudad}) atrasado {$diasAtrasado} día(s)";
        }

        // pedidos_proximos_entregar
        $dias = abs($diasRestantes);
        if ($dias == 0) {
            return "Pedido #{$pedido->id} para {$clienteNombre} ({$ciudad}) a entregar HOY";
        }
        return "Pedido #{$pedido->id} para {$clienteNombre} ({$ciudad}) a entregar en {$dias} día(s)";
    }

    /**
     * Calcula nivel de prioridad para alertas de cheques
     */
    protected function calculateChequeLevel(?int $diasRestantes, string $tipo): string
    {
        if ($tipo === 'cheques_vencidos') {
            return 'critical';
        }

        if ($diasRestantes === null) {
            return 'info';
        }

        $niveles = config('alerts.cheques.niveles');
        $dias = abs($diasRestantes);

        if ($dias <= $niveles['critico']) {
            return 'critical';
        }
        if ($dias <= $niveles['alto']) {
            return 'warning';
        }
        if ($dias <= $niveles['moderado']) {
            return 'info';
        }

        return 'info';
    }

    /**
     * Calcula nivel de prioridad para alertas de pedidos
     */
    protected function calculatePedidoLevel(?int $diasRestantes, string $tipo): string
    {
        if ($tipo === 'pedidos_atrasados') {
            return 'critical';
        }

        if ($diasRestantes === null) {
            return 'info';
        }

        $niveles = config('alerts.pedidos.niveles');
        $dias = abs($diasRestantes);

        if ($dias <= $niveles['critico']) {
            return 'critical';
        }
        if ($dias <= $niveles['alto']) {
            return 'warning';
        }
        if ($dias <= $niveles['moderado']) {
            return 'info';
        }

        return 'info';
    }

    /**
     * Pagina una colección manualmente
     */
    protected function paginateCollection($items, int $perPage)
    {
        $page = request()->get('page', 1);
        $offset = ($page - 1) * $perPage;
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items->slice($offset, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
