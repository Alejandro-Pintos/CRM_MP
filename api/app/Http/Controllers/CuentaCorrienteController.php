<?php
namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Venta;
use App\Models\Pago;
use Illuminate\Http\Request;

class CuentaCorrienteController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
        // Si manejás permisos, descomenta:
        // $this->middleware('permission:cta_cte.show')->only('show');
    }

    /**
     * GET /api/v1/clientes/{cliente}/cuenta-corriente
     * Query params opcionales: ?desde=YYYY-MM-DD&hasta=YYYY-MM-DD
     */
    public function show(Request $request, Cliente $cliente)
    {
        $desde = $request->date('desde');
        $hasta = $request->date('hasta');

        // === Ventas del cliente (monto positivo) ===
        $ventasQ = Venta::query()
            ->select(['id','fecha','total'])
            ->where('cliente_id', $cliente->id);

        if ($desde) $ventasQ->whereDate('fecha', '>=', $desde);
        if ($hasta) $ventasQ->whereDate('fecha', '<=', $hasta);

        $ventas = $ventasQ->get()->map(function ($v) {
            return [
                'fecha'         => (string)$v->fecha,
                'tipo'          => 'venta',
                'referencia_id' => $v->id,
                'descripcion'   => 'Venta #'.$v->id,
                'monto'         => (float)$v->total,   // + total
            ];
        });

        // === Pagos de esas ventas (monto negativo) ===
        $pagosQ = Pago::query()
            ->select(['pagos.id','pagos.venta_id','pagos.fecha_pago','pagos.monto'])
            ->whereHas('venta', function ($q) use ($cliente, $desde, $hasta) {
                $q->where('cliente_id', $cliente->id);
                // NOTA: el filtro de fechas para pagos va sobre fecha_pago, no sobre la fecha de la venta
            });

        if ($desde) $pagosQ->whereDate('fecha_pago', '>=', $desde);
        if ($hasta) $pagosQ->whereDate('fecha_pago', '<=', $hasta);

        $pagos = $pagosQ->get()->map(function ($p) {
            return [
                'fecha'         => (string)$p->fecha_pago,
                'tipo'          => 'pago',
                'referencia_id' => $p->id,
                'venta_id'      => $p->venta_id,
                'descripcion'   => 'Pago venta #'.$p->venta_id,
                'monto'         => - (float)$p->monto, // negativo
            ];
        });

        // === Merge + ordenar por fecha y calcular saldo acumulado ===
        $movimientos = $ventas->concat($pagos)->sortBy('fecha')->values()->all();

        $saldo = 0.0;
        $totalVentas = 0.0;
        $totalPagos  = 0.0;

        foreach ($movimientos as &$m) {
            if ($m['tipo'] === 'venta') {
                $totalVentas += $m['monto'];
            } else {
                $totalPagos  += abs($m['monto']);
            }
            $saldo += $m['monto'];
            $m['saldo_acumulado'] = round($saldo, 2);
        }
        unset($m);

        return response()->json([
            'cliente' => [
                'id'       => $cliente->id,
                'nombre'   => $cliente->nombre,
                'apellido' => $cliente->apellido,
            ],
            'filtros' => [
                'desde' => $desde?->toDateString(),
                'hasta' => $hasta?->toDateString(),
            ],
            'resumen' => [
                'total_ventas' => round($totalVentas, 2),
                'total_pagos'  => round($totalPagos, 2),
                'saldo_actual' => round($totalVentas - $totalPagos, 2),
            ],
            'movimientos' => $movimientos,
        ]);
    }
}
