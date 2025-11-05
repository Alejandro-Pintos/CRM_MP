<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'venta_id'       => $this->venta_id,
            'metodo_pago_id' => $this->metodo_pago_id,
            'metodo_pago'    => $this->whenLoaded('metodoPago', function() {
                return [
                    'id' => $this->metodoPago->id,
                    'nombre' => $this->metodoPago->nombre,
                ];
            }),
            'monto'          => (float) $this->monto,
            'fecha'          => $this->fecha_pago?->format('Y-m-d'),
            'fecha_pago'     => $this->fecha_pago?->format('Y-m-d'),
            'created_at'     => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
