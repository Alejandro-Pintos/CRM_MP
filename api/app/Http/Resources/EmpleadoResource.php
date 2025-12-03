<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpleadoResource extends JsonResource
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
            'nombre_completo' => $this->nombre_completo,
            'documento' => $this->documento,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'direccion' => $this->direccion,
            'puesto' => $this->puesto,
            'notas' => $this->notas,
            'activo' => (bool) $this->activo,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            
            // Relaciones opcionales
            'total_pagos' => $this->whenLoaded('pagos', function() {
                return $this->pagos->sum('monto');
            }),
            'cantidad_pagos' => $this->whenLoaded('pagos', function() {
                return $this->pagos->count();
            }),
        ];
    }
}
