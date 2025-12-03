<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChequeEmitidoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'proveedor' => [
                'id' => $this->proveedor_id,
                'nombre' => $this->proveedor?->nombre,
                'cuit' => $this->proveedor?->cuit,
            ],
            'banco' => $this->banco,
            'numero' => $this->numero,
            'monto' => (float) $this->monto,
            'fecha_emision' => $this->fecha_emision?->format('Y-m-d'),
            'fecha_vencimiento' => $this->fecha_vencimiento?->format('Y-m-d'),
            'estado' => $this->estado,
            'fecha_cobro' => $this->fecha_cobro?->format('Y-m-d'),
            'observaciones' => $this->observaciones,
            'pago_proveedor_id' => $this->pago_proveedor_id,
            'pago_proveedor' => $this->when($this->relationLoaded('pagoProveedor'), function () {
                return [
                    'id' => $this->pagoProveedor?->id,
                    'concepto' => $this->pagoProveedor?->concepto,
                    'fecha_pago' => $this->pagoProveedor?->fecha_pago?->format('Y-m-d'),
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
