<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Usamos saldo_calculado para asegurar precisión
        $saldoActual = $this->saldo_calculado ?? $this->saldo_actual;
        $limiteCredito = (float)$this->limite_credito;
        
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'nombre_completo' => trim($this->nombre . ' ' . $this->apellido),
            'cuit_cuil' => $this->cuit_cuil,
            'telefono' => $this->telefono,
            'email' => $this->email,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'provincia' => $this->provincia,
            'estado' => $this->estado,
            'saldo_actual' => $saldoActual,
            'limite_credito' => $limiteCredito,
            'disponible' => round($limiteCredito - $saldoActual, 2),
            'requiere_factura' => $this->requiere_factura,
            'fecha_registro' => $this->fecha_registro?->toDateTimeString(),
            'fecha_ultima_compra' => $this->fecha_ultima_compra?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
