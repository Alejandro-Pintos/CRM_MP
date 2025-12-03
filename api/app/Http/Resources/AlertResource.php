<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para transformar alertas a formato JSON API
 */
class AlertResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'tipo' => $this->resource['tipo'],
            'entidad' => $this->resource['entidad'],
            'entidad_id' => $this->resource['entidad_id'],
            'mensaje' => $this->resource['mensaje'],
            'nivel' => $this->resource['nivel'],
            'fecha_referencia' => $this->resource['fecha_referencia'],
            'dias_restantes' => $this->resource['dias_restantes'],
            
            // Datos específicos según tipo de entidad
            'datos' => $this->getDatosEspecificos(),
            
            // Cliente relacionado (común a ambos tipos)
            'cliente' => $this->resource['cliente'] ?? null,
            
            // Metadatos
            'venta_id' => $this->resource['venta_id'] ?? null,
        ];
    }

    /**
     * Obtiene datos específicos según el tipo de entidad
     */
    protected function getDatosEspecificos(): array
    {
        if ($this->resource['entidad'] === 'cheque') {
            return [
                'monto' => $this->resource['monto'] ?? null,
                'numero_cheque' => $this->resource['numero_cheque'] ?? null,
            ];
        }

        if ($this->resource['entidad'] === 'pedido') {
            return [
                'estado' => $this->resource['estado'] ?? null,
                'ciudad_entrega' => $this->resource['ciudad_entrega'] ?? null,
            ];
        }

        return [];
    }
}
