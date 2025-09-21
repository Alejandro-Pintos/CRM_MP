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
            'email' => $this->email,
            'telefono' => $this->telefono,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'provincia' => $this->provincia,
            'cuit_cuil' => $this->cuit_cuil, // ✅ corregido
            'estado' => $this->estado,
            'saldo_actual' => $this->saldo_actual,
            'limite_credito' => $this->limite_credito,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
