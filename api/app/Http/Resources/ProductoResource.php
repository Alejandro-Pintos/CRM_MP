<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
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
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'unidad_medida' => $this->unidad_medida,
            'precio_compra' => (float) $this->precio_compra,
            'precio_venta' => (float) $this->precio_venta,
            'precio' => (float) $this->precio,
            'precio_total' => $this->precio_total, // Calculado dinámicamente
            'iva' => (float) $this->iva,
            'precio_con_iva' => (float) $this->precio_con_iva, // Legacy - mantener compatibilidad
            'estado' => $this->estado,
            'proveedor_id' => $this->proveedor_id,

            // timestamps
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }
}
