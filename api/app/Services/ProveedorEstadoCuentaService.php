<?php

namespace App\Services;

use App\Models\Proveedor;
use App\Models\Compra;
use App\Models\PagoProveedor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProveedorEstadoCuentaService
{
    /**
     * Obtener resumen de cuenta de un proveedor
     * 
     * @param int $proveedorId
     * @return array
     */
    public function getResumen(int $proveedorId): array
    {
        $proveedor = Proveedor::findOrFail($proveedorId);
        
        // Total de compras (facturas de compra)
        $totalCompras = $proveedor->compras()
            ->where('estado', '!=', 'anulado')
            ->sum('monto_total');
        
        // Total de pagos realizados
        $totalPagos = $proveedor->pagos()->sum('monto');
        
        // Calcular saldo
        $saldo = $totalCompras - $totalPagos;
        
        // Determinar estado
        $estado = 'al_dia';
        if ($saldo > 0) {
            $estado = 'deuda'; // Debemos al proveedor
        } elseif ($saldo < 0) {
            $estado = 'saldo_a_favor'; // Proveedor nos debe (devoluciones, notas de crédito)
        }
        
        return [
            'proveedor_id' => $proveedorId,
            'total_compras' => (float) $totalCompras,
            'total_pagos' => (float) $totalPagos,
            'saldo' => (float) $saldo,
            'saldo_absoluto' => (float) abs($saldo),
            'estado' => $estado,
            'estado_texto' => $this->getEstadoTexto($estado, $saldo),
        ];
    }

    /**
     * Obtener movimientos de cuenta corriente de un proveedor
     * 
     * @param int $proveedorId
     * @param Carbon|null $desde
     * @param Carbon|null $hasta
     * @return Collection
     */
    public function getMovimientos(int $proveedorId, ?Carbon $desde = null, ?Carbon $hasta = null): Collection
    {
        $proveedor = Proveedor::findOrFail($proveedorId);
        
        // Obtener compras
        $compras = $proveedor->compras()
            ->where('estado', '!=', 'anulado')
            ->when($desde, fn($q) => $q->where('fecha_compra', '>=', $desde))
            ->when($hasta, fn($q) => $q->where('fecha_compra', '<=', $hasta))
            ->get()
            ->map(function($compra) {
                return [
                    'id' => $compra->id,
                    'fecha' => $compra->fecha_compra->format('Y-m-d'),
                    'tipo' => 'COMPRA',
                    'tipo_texto' => 'Compra/Factura',
                    'descripcion' => 'Factura de compra #' . $compra->id,
                    'referencia' => '#' . $compra->id,
                    'debito' => (float) $compra->monto_total,
                    'credito' => 0,
                    'estado' => $compra->estado,
                ];
            });
        
        // Obtener pagos
        $pagos = $proveedor->pagos()
            ->with('metodoPago')
            ->when($desde, fn($q) => $q->where('fecha_pago', '>=', $desde))
            ->when($hasta, fn($q) => $q->where('fecha_pago', '<=', $hasta))
            ->get()
            ->map(function($pago) {
                return [
                    'id' => $pago->id,
                    'fecha' => $pago->fecha_pago->format('Y-m-d'),
                    'tipo' => 'PAGO',
                    'tipo_texto' => 'Pago',
                    'descripcion' => $pago->concepto . ($pago->metodoPago ? ' - ' . $pago->metodoPago->nombre : ''),
                    'referencia' => $pago->referencia ?? '-',
                    'debito' => 0,
                    'credito' => (float) $pago->monto,
                    'metodo_pago' => $pago->metodoPago?->nombre,
                ];
            });
        
        // Combinar y ordenar por fecha
        $movimientos = $compras->concat($pagos)->sortBy('fecha')->values();
        
        // Calcular saldo acumulado
        $saldoAcumulado = 0;
        $movimientos = $movimientos->map(function($mov) use (&$saldoAcumulado) {
            $saldoAcumulado += $mov['debito'] - $mov['credito'];
            $mov['saldo_acumulado'] = (float) $saldoAcumulado;
            return $mov;
        });
        
        return $movimientos;
    }

    /**
     * Obtener texto descriptivo del estado
     * 
     * @param string $estado
     * @param float $saldo
     * @return string
     */
    private function getEstadoTexto(string $estado, float $saldo): string
    {
        return match($estado) {
            'deuda' => 'Deuda: $' . number_format(abs($saldo), 2, ',', '.'),
            'saldo_a_favor' => 'Saldo a favor: $' . number_format(abs($saldo), 2, ',', '.'),
            'al_dia' => 'Al día',
            default => 'Desconocido',
        };
    }
}
