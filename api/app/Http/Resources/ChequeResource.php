<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChequeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // BUG 2: Calcular datos dinámicos SIEMPRE (no solo cuando están disponibles)
        $diasRestantes = $this->calcularDiasRestantes();
        $estadoAlerta = $this->obtenerEstadoAlerta();
        
        return [
            'id' => $this->id,
            
            // Campos principales (usando los nombres que espera el frontend)
            'numero_cheque' => $this->numero,
            'numero' => $this->numero, // Mantener por compatibilidad
            'monto' => (float)$this->monto,
            'estado_cheque' => $this->estado,
            'estado' => $this->estado, // Mantener por compatibilidad
            
            // Fechas (usar nombres del frontend)
            'fecha_cheque' => $this->fecha_emision?->format('Y-m-d'),
            'fecha_emision' => $this->fecha_emision?->format('Y-m-d'), // Compatibilidad
            
            // BUG 2 CRITICAL: Frontend usa fecha_cobro para mostrar fecha de vencimiento
            'fecha_cobro' => $this->fecha_vencimiento?->format('Y-m-d'), // Fecha ESTIMADA de cobro (vencimiento)
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'), // Compatibilidad
            'fecha_cobro_real' => $this->fecha_cobro?->format('Y-m-d'), // Fecha REAL de cobro (cuando se cobró efectivamente)
            'fecha_procesado' => $this->fecha_cobro?->format('Y-m-d') ?? $this->fecha_rechazo?->format('Y-m-d'),
            'fecha_rechazo' => $this->fecha_rechazo?->format('Y-m-d'),
            
            // Observaciones
            'observaciones_cheque' => $this->observaciones,
            'observaciones' => $this->observaciones, // Compatibilidad
            'motivo_rechazo' => $this->motivo_rechazo,
            
            // BUG 2: Datos calculados SIEMPRE (nunca undefined)
            // Si no hay fecha_vencimiento, dias_restantes es null
            'dias_restantes' => $diasRestantes,
            'vencido' => $this->estaVencido(),
            'proximo_a_vencer' => $this->estaProximoAVencer(),
            
            // BUG 2: estado_alerta ahora devuelve 'sin_fecha' si no hay fecha_vencimiento
            // En lugar de mostrar "Sin fecha definida" en frontend
            'estado_alerta' => $estadoAlerta,
            
            // Relaciones
            'venta_id' => $this->venta_id, // ID directo para tabla
            'venta' => $this->when($this->relationLoaded('venta'), [
                'id' => $this->venta?->id,
                'numero' => $this->venta?->numero_comprobante ?? "Venta #{$this->venta?->id}",
                'total' => (float)($this->venta?->total ?? 0),
                'fecha' => $this->venta?->fecha?->format('Y-m-d'),
            ]),
            
            'cliente_id' => $this->cliente_id, // ID directo
            'cliente' => $this->when($this->relationLoaded('cliente'), [
                'id' => $this->cliente?->id,
                'nombre' => $this->cliente?->nombre_completo ?? $this->cliente?->nombre,
            ]),
            
            'pago' => $this->when($this->relationLoaded('pago'), [
                'id' => $this->pago?->id,
                'fecha_pago' => $this->pago?->fecha_pago?->format('Y-m-d'),
            ]),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
