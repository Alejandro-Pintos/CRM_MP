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
            'cuit_dni' => $this->cuit_dni,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'direccion' => $this->direccion,
            'saldo_actual' => $this->saldo_actual,
            'limite_credito' => $this->limite_credito,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
