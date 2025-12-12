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
            'metodo_pago'    => $this->whenLoaded('metodoPago', function() {
                return [
                    'id' => $this->metodoPago->id,
                    'nombre' => $this->metodoPago->nombre,
                ];
            }),
            'monto'          => (float) $this->monto,
            'fecha'          => $this->fecha_pago instanceof \Carbon\Carbon 
                ? $this->fecha_pago->format('Y-m-d') 
                : $this->fecha_pago,
            'fecha_pago'     => $this->fecha_pago instanceof \Carbon\Carbon 
                ? $this->fecha_pago->format('Y-m-d') 
                : $this->fecha_pago,
            // Campos de cheque - Priorizar datos de la tabla cheques
            'estado_cheque'  => $this->whenLoaded('cheque', $this->cheque?->estado, $this->estado_cheque),
            'numero_cheque'  => $this->whenLoaded('cheque', $this->cheque?->numero, $this->numero_cheque),
            'fecha_cheque'   => $this->whenLoaded('cheque', function() {
                return $this->cheque?->fecha_emision instanceof \Carbon\Carbon 
                    ? $this->cheque->fecha_emision->format('Y-m-d') 
                    : $this->cheque?->fecha_emision;
            }, $this->fecha_cheque instanceof \Carbon\Carbon 
                ? $this->fecha_cheque->format('Y-m-d') 
                : $this->fecha_cheque),
            'fecha_cobro'    => $this->whenLoaded('cheque', function() {
                return $this->cheque?->fecha_vencimiento instanceof \Carbon\Carbon 
                    ? $this->cheque->fecha_vencimiento->format('Y-m-d') 
                    : $this->cheque?->fecha_vencimiento;
            }, $this->fecha_cobro instanceof \Carbon\Carbon 
                ? $this->fecha_cobro->format('Y-m-d') 
                : $this->fecha_cobro),
            'observaciones_cheque' => $this->whenLoaded('cheque', $this->cheque?->observaciones, $this->observaciones_cheque),
            'created_at'     => $this->created_at instanceof \Carbon\Carbon 
                ? $this->created_at->format('Y-m-d H:i:s') 
                : $this->created_at,
        ];
    }
}
