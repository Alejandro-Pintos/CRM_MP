<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Asegurar que la relación pagos esté cargada
        $this->resource->loadMissing('pagos');
        
        $total = (float) ($this->total ?? 0);

        // Calcular pagos reales (excluyendo cuenta corriente)
        $totalPagado = 0.0;
        $totalCuentaCorriente = 0.0;
        $totalChequesPendientes = 0.0;
        
        try {
            // Obtener ID de método "Cuenta Corriente"
            $cuentaCorrienteId = \App\Models\MetodoPago::where('nombre', 'Cuenta Corriente')->value('id');
            
            if ($cuentaCorrienteId && $this->relationLoaded('pagos')) {
                // Usar la relación cargada en lugar de hacer queries
                $pagos = $this->pagos;
                
                // Pagos reales: sin cuenta corriente Y solo cheques cobrados
                $totalPagado = (float) $pagos
                    ->where('metodo_pago_id', '!=', $cuentaCorrienteId)
                    ->filter(function($pago) {
                        return is_null($pago->estado_cheque) || $pago->estado_cheque === 'cobrado';
                    })
                    ->sum('monto');
                
                // Cheques pendientes (no cobrados aún)
                $totalChequesPendientes = (float) $pagos
                    ->where('metodo_pago_id', '!=', $cuentaCorrienteId)
                    ->where('estado_cheque', 'pendiente')
                    ->sum('monto');
                
                // Total a cuenta corriente (deuda)
                $totalCuentaCorriente = (float) $pagos
                    ->where('metodo_pago_id', $cuentaCorrienteId)
                    ->sum('monto');
            } else {
                // Si no existe el método, contar todos menos cheques pendientes
                $totalPagado = (float) $this->resource->pagos()
                    ->where(function($query) {
                        $query->whereNull('estado_cheque')
                              ->orWhere('estado_cheque', 'cobrado');
                    })
                    ->sum('monto');
                    
                $totalChequesPendientes = (float) $this->resource->pagos()
                    ->where('estado_cheque', 'pendiente')
                    ->sum('monto');
            }
        } catch (\Throwable $e) {
            $totalPagado = 0.0;
            $totalCuentaCorriente = 0.0;
            $totalChequesPendientes = 0.0;
        }

        // Saldo pendiente = lo que falta pagar en efectivo/real
        // (el total menos pagos reales menos lo que ya está en cuenta corriente como deuda)
        $saldoPendiente = round($total - $totalPagado - $totalCuentaCorriente, 2);
        
        // CRÍTICO: Calcular deuda CC REAL desde movimientos de cuenta corriente
        $deudaCCReal = 0.0;
        try {
            $service = new \App\Services\CuentaCorrienteService();
            $deudaCCReal = $service->calcularDeudaCCVenta($this->id);
        } catch (\Throwable $e) {
            \Log::error("Error calculando deuda CC de venta #{$this->id}: " . $e->getMessage());
            // Fallback: usar el total de pagos CC como aproximación
            $deudaCCReal = $totalCuentaCorriente;
        }

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
            'fecha'           => $this->fecha instanceof \Carbon\Carbon 
                ? $this->fecha->format('Y-m-d') 
                : $this->fecha,
            'total'           => $total,
            'total_pagado'    => round($totalPagado, 2),
            'total_cheques_pendientes' => round($totalChequesPendientes, 2),
            'total_cuenta_corriente' => round($totalCuentaCorriente, 2), // Monto ORIGINAL asignado a CC
            'deuda_cc_pendiente' => round($deudaCCReal, 2), // Deuda CC ACTUAL (después de pagos)
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
            'created_at'      => $this->created_at instanceof \Carbon\Carbon 
                ? $this->created_at->format('Y-m-d H:i:s') 
                : $this->created_at,
            'updated_at'      => $this->updated_at instanceof \Carbon\Carbon 
                ? $this->updated_at->format('Y-m-d H:i:s') 
                : $this->updated_at,
        ];
    }
}
