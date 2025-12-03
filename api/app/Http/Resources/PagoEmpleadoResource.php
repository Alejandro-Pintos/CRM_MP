<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoEmpleadoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'empleado_id' => $this->empleado_id,
            'fecha_pago' => $this->fecha_pago?->format('Y-m-d'),
            'monto' => (float) $this->monto,
            'metodo_pago_id' => $this->metodo_pago_id,
            'metodo_pago' => $this->whenLoaded('metodoPago', function() {
                return [
                    'id' => $this->metodoPago->id,
                    'nombre' => $this->metodoPago->nombre,
                ];
            }),
            'concepto' => $this->concepto,
            'observaciones' => $this->observaciones,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            
            // Empleado (opcional)
            'empleado' => $this->whenLoaded('empleado', function() {
                return [
                    'id' => $this->empleado->id,
                    'nombre_completo' => $this->empleado->nombre_completo,
                    'documento' => $this->empleado->documento,
                ];
            }),
        ];
    }
}
