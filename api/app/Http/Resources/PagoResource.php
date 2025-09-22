<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'venta_id'       => $this->venta_id,
            'metodo_pago_id' => $this->metodo_pago_id,
            'monto'          => $this->monto,
            'fecha_pago'     => $this->fecha_pago,
            'created_at'     => $this->created_at,
        ];
    }
}
