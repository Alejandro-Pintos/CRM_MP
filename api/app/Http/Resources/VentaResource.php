<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $total = (float) ($this->total ?? 0);

        // No tocamos controller: si hay relación pagos, bien; si no, consultamos suma rápido.
        $totalPagado = 0.0;
        try {
            $totalPagado = (float) $this->resource->pagos()->sum('monto');
        } catch (\Throwable $e) {
            $totalPagado = 0.0;
        }

        $saldoPendiente = round($total - $totalPagado, 2);

        return [
            'id'              => $this->id,
            'cliente_id'      => $this->cliente_id,
            'cliente_nombre'  => $this->whenLoaded('cliente', function () {
                return $this->cliente->nombre . ' ' . $this->cliente->apellido;
            }),
            'cliente'         => $this->whenLoaded('cliente', function () {
                return [
                    'id'       => $this->cliente->id,
                    'nombre'   => $this->cliente->nombre,
                    'apellido' => $this->cliente->apellido,
                    'email'    => $this->cliente->email,
                ];
            }),
            'usuario_id'      => $this->usuario_id,
            'fecha'           => $this->fecha,
            'total'           => $total,
            'total_pagado'    => round($totalPagado, 2),
            'saldo_pendiente' => $saldoPendiente,
            'estado_pago'     => $this->estado_pago,
            'tipo_comprobante' => $this->tipo_comprobante,
            'numero_comprobante' => $this->numero_comprobante,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($it) {
                    return [
                        'id'              => $it->id,
                        'producto_id'     => $it->producto_id,
                        'cantidad'        => (float) $it->cantidad,
                        'precio_unitario' => (float) $it->precio_unitario,
                        'iva'             => (float) ($it->iva ?? 0),
                    ];
                });
            }),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
