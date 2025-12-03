<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProveedorResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'nombre'    => $this->nombre,
            'cuit'      => $this->cuit,
            'direccion' => $this->direccion,
            'telefono'  => $this->telefono,
            'email'     => $this->email,
            'estado'    => $this->estado,
            
            // Resumen de estado de cuenta (solo cuando se carga la relación)
            'total_compras' => $this->whenLoaded('compras', function() {
                return (float) $this->compras->where('estado', '!=', 'anulado')->sum('monto_total');
            }),
            'total_pagos' => $this->whenLoaded('pagos', function() {
                return (float) $this->pagos->sum('monto');
            }),
            
            'created_at'=> $this->created_at,
            'updated_at'=> $this->updated_at,
        ];
    }
}
