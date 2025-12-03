<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovimientoCtaCteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'tipo'         => $this->tipo,
            'referencia_id'=> $this->referencia_id,
            'monto'        => $this->monto,
            'fecha'        => $this->fecha,
            'descripcion'  => $this->descripcion,
            'created_at'   => $this->created_at,
        ];
    }
}
