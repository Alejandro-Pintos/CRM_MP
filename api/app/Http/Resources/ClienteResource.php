<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'cuit_cuil' => $this->cuit_cuil,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'provincia' => $this->provincia,
            'estado' => $this->estado,
            'saldo_actual' => $this->saldo_actual,
            'limite_credito' => $this->limite_credito,
            'fecha_registro' => $this->fecha_registro?->toDateTimeString(),
            'fecha_ultima_compra' => $this->fecha_ultima_compra?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
