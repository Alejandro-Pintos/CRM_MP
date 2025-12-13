<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompraResource extends JsonResource
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
            'fecha_compra' => $this->fecha_compra?->format('Y-m-d'),
            'estado' => $this->estado,
            'metodo_pago' => $this->metodo_pago,
            'subtotal' => (float) $this->subtotal,
            'descuento_global' => (float) $this->descuento_global,
            'impuestos_total' => (float) $this->impuestos_total,
            'monto_total' => (float) $this->monto_total,
            'observaciones' => $this->observaciones,
            
            // Relaciones opcionales
            'proveedor' => $this->whenLoaded('proveedor', function() {
                return [
                    'id' => $this->proveedor->id,
                    'nombre' => $this->proveedor->nombre,
                    'cuit' => $this->proveedor->cuit,
                ];
            }),
            
            'detalles' => $this->whenLoaded('detalles', function() {
                return $this->detalles->map(function($detalle) {
                    return [
                        'id' => $detalle->id,
                        'producto_id' => $detalle->producto_id,
                        'descripcion' => $detalle->descripcion,
                        'unidad_medida' => $detalle->unidad_medida,
                        'cantidad' => (float) $detalle->cantidad,
                        'precio_unitario' => (float) $detalle->precio_unitario,
                        'descuento_item' => (float) $detalle->descuento_item,
                        'impuesto_porcentaje' => (float) $detalle->impuesto_porcentaje,
                        'impuesto_monto' => (float) $detalle->impuesto_monto,
                        'subtotal' => (float) $detalle->subtotal,
                    ];
                });
            }),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
