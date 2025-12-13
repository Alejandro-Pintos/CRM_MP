<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoProveedorResource extends JsonResource
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
            'proveedor_id' => $this->proveedor_id,
            'fecha_pago' => $this->fecha_pago?->format('Y-m-d'),
            'monto' => (float) $this->monto,
            'metodo_pago_id' => $this->metodo_pago_id,
            'referencia' => $this->referencia,
            'concepto' => $this->concepto,
            'observaciones' => $this->observaciones,
            'usuario_id' => $this->usuario_id,
            
            // Relaciones opcionales
            'metodo_pago' => $this->whenLoaded('metodoPago', function() {
                return [
                    'id' => $this->metodoPago->id,
                    'nombre' => $this->metodoPago->nombre,
                ];
            }),
            
            'proveedor' => $this->whenLoaded('proveedor', function() {
                return [
                    'id' => $this->proveedor->id,
                    'nombre' => $this->proveedor->nombre,
                    'cuit' => $this->proveedor->cuit,
                ];
            }),
            
            'cheque' => $this->whenLoaded('cheque', function() {
                if (!$this->cheque) return null;
                return [
                    'id' => $this->cheque->id,
                    'banco' => $this->cheque->banco,
                    'numero' => $this->cheque->numero,
                    'fecha_emision' => $this->cheque->fecha_emision?->format('Y-m-d'),
                    'fecha_vencimiento' => $this->cheque->fecha_vencimiento?->format('Y-m-d'),
                    'estado' => $this->cheque->estado,
                    'observaciones' => $this->cheque->observaciones,
                ];
            }),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
