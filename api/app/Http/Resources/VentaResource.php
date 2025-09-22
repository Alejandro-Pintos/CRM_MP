<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'usuario_id' => $this->usuario_id,
            'fecha' => $this->fecha,
            'total' => $this->total,
            'estado_pago' => $this->estado_pago,
            'tipo_comprobante' => $this->tipo_comprobante,
            'numero_comprobante' => $this->numero_comprobante,
            'items' => $this->items->map(fn($it) => [
                'id' => $it->id,
                'producto_id' => $it->producto_id,
                'cantidad' => $it->cantidad,
                'precio_unitario' => $it->precio_unitario,
                'iva' => $it->iva,
                'subtotal_con_iva' => round($it->cantidad * $it->precio_unitario * (1 + ($it->iva ?? 0)/100), 2),
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
