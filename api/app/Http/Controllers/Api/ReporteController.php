<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

// Exports existentes (según tu proyecto)
use App\Exports\ProveedoresRankingExport;
use App\Exports\VentasExport;
use App\Exports\ClientesRankingExport;
use App\Exports\ProductosRankingExport;
use App\Exports\VentasReportExport;

class ReporteController extends Controller
{
    /**
     * Reporte JSON de ventas (KPIs + series)
     */
    public function ventas(Request $request)
    {
        $request->validate([
            'from'        => ['nullable','date'],
            'to'          => ['nullable','date','after_or_equal:from'],
            'group_by'    => ['nullable','in:day,month'],
            'cliente_id'  => ['nullable','integer','min:1'],
            'producto_id' => ['nullable','integer','min:1'],
            'metodo_pago' => ['nullable','string','max:50'],
            'tz'          => ['nullable','string','max:64'],
        ]);

        $from       = $request->input('from');
        $to         = $request->input('to');
        $groupBy    = $request->input('group_by', 'day');
        $clienteId  = $request->input('cliente_id');
        $productoId = $request->input('producto_id');
        $metodoPago = $request->input('metodo_pago');
        $tz         = $request->input('tz', 'America/Argentina/Buenos_Aires');

        $TOTAL_COL = 'total';

        $q = Venta::query();

        if ($from)      $q->whereDate('fecha', '>=', $from);
        if ($to)        $q->whereDate('fecha', '<=', $to);
        if ($clienteId) $q->where('cliente_id', $clienteId);

        if ($productoId) {
            $q->whereHas('items', function ($iq) use ($productoId) {
                $iq->where('producto_id', $productoId);
            });
        }

        if ($metodoPago) {
            $q->whereHas('pagos', function ($pq) use ($metodoPago) {
                $pq->where('method', $metodoPago);
            });
        }

        // KPIs
        $kpis = (clone $q)
            ->selectRaw("COALESCE(SUM($TOTAL_COL),0) as total, COUNT(*) as cnt")
            ->first();

        $total = (float) ($kpis->total ?? 0);
        $cnt   = (int)   ($kpis->cnt   ?? 0);
        $ticketProm = $cnt > 0 ? round($total / $cnt, 2) : 0.0;

        // Series con información enriquecida
        if ($groupBy === 'month') {
            $seriesBase = (clone $q)
                ->selectRaw("DATE_FORMAT(fecha, '%Y-%m-01') as period, COALESCE(SUM($TOTAL_COL),0) as total, COUNT(*) as cnt")
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        } else {
            $seriesBase = (clone $q)
                ->selectRaw("DATE(fecha) as period, COALESCE(SUM($TOTAL_COL),0) as total, COUNT(*) as cnt")
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        }

        $series = $seriesBase->map(function($r) use ($from, $to, $groupBy) {
            $period = $r->period;
            $total = (float) $r->total;
            $cnt = (int) $r->cnt;
            $ticketProm = $cnt > 0 ? round($total / $cnt, 2) : 0;

            // Filtrar ventas del período
            $ventasQuery = DB::table('ventas as v')
                ->whereNull('v.deleted_at');
            
            if ($groupBy === 'month') {
                $ventasQuery->whereRaw("DATE_FORMAT(v.fecha, '%Y-%m-01') = ?", [$period]);
            } else {
                $ventasQuery->whereDate('v.fecha', $period);
            }

            // Clientes únicos
            $clientesUnicos = (clone $ventasQuery)->distinct('cliente_id')->count('cliente_id');

            // Total productos vendidos
            $productosVendidos = DB::table('ventas as v')
                ->join('detalle_venta as d', 'd.venta_id', '=', 'v.id')
                ->whereNull('v.deleted_at')
                ->when($groupBy === 'month', 
                    fn($q) => $q->whereRaw("DATE_FORMAT(v.fecha, '%Y-%m-01') = ?", [$period]),
                    fn($q) => $q->whereDate('v.fecha', $period)
                )
                ->sum('d.cantidad');

            // Estados de pago
            $estadoPagos = DB::table('ventas as v')
                ->whereNull('v.deleted_at')
                ->when($groupBy === 'month', 
                    fn($q) => $q->whereRaw("DATE_FORMAT(v.fecha, '%Y-%m-01') = ?", [$period]),
                    fn($q) => $q->whereDate('v.fecha', $period)
                )
                ->selectRaw('estado_pago, COUNT(*) as cantidad')
                ->groupBy('estado_pago')
                ->get()
                ->pluck('cantidad', 'estado_pago')
                ->toArray();

            return [
                'period'            => $period,
                'total_neto'        => $total,
                'ventas_count'      => $cnt,
                'ticket_promedio'   => $ticketProm,
                'clientes_unicos'   => (int) $clientesUnicos,
                'productos_vendidos' => (float) ($productosVendidos ?? 0),
                'pagado'            => (int) ($estadoPagos['pagado'] ?? 0),
                'pendiente'         => (int) ($estadoPagos['pendiente'] ?? 0),
                'parcial'           => (int) ($estadoPagos['parcial'] ?? 0),
            ];
        })->all();

        return response()->json([
            'range' => ['from'=>$from, 'to'=>$to, 'tz'=>$tz],
            'filters' => [
                'cliente_id'  => $clienteId,
                'producto_id' => $productoId,
                'metodo_pago' => $metodoPago,
                'group_by'    => $groupBy,
            ],
            'kpis' => [
                'total_neto'         => $total,
                'ventas_count'       => $cnt,
                'ticket_promedio'    => $ticketProm,
                'clientes_unicos'    => (clone $q)->distinct('cliente_id')->count('cliente_id'),
                'productos_vendidos' => DB::table('ventas as v')
                    ->join('detalle_venta as d', 'd.venta_id', '=', 'v.id')
                    ->whereIn('v.id', (clone $q)->pluck('id'))
                    ->sum('d.cantidad'),
            ],
            'series' => $series,
        ], Response::HTTP_OK);
    }

    /**
     * TOP Clientes (JSON)
     */
    public function clientes(Request $request)
    {
        $request->validate([
            'from'  => ['nullable','date'],
            'to'    => ['nullable','date','after_or_equal:from'],
            'limit' => ['nullable','integer','min:1','max:100'],
        ]);

        $from  = $request->input('from');
        $to    = $request->input('to');
        $limit = (int) $request->input('limit', 10);

        $rows = DB::table('ventas as v')
            ->join('clientes as c', 'c.id', '=', 'v.cliente_id')
            ->whereNull('v.deleted_at')
            ->when($from, fn($q) => $q->whereDate('v.fecha','>=',$from))
            ->when($to,   fn($q) => $q->whereDate('v.fecha','<=',$to))
            ->selectRaw('
                v.cliente_id,
                c.nombre,
                c.apellido,
                c.email,
                c.telefono,
                c.cuit_cuil,
                c.estado,
                c.saldo_actual,
                c.limite_credito,
                COUNT(*) as compras,
                SUM(v.total) as ingreso_total
            ')
            ->groupBy('v.cliente_id','c.nombre','c.apellido','c.email','c.telefono','c.cuit_cuil','c.estado','c.saldo_actual','c.limite_credito')
            ->orderByDesc('ingreso_total')
            ->limit($limit)
            ->get();

        return response()->json([
            'range' => ['from'=>$from, 'to'=>$to],
            'limit' => $limit,
            'data'  => $rows,
        ], Response::HTTP_OK);
    }

    /**
     * TOP Productos (JSON)
     */
    public function productos(Request $request)
    {
        $request->validate([
            'from'  => ['nullable','date'],
            'to'    => ['nullable','date','after_or_equal:from'],
            'limit' => ['nullable','integer','min:1','max:100'],
        ]);

        $from  = $request->input('from');
        $to    = $request->input('to');
        $limit = (int) $request->input('limit', 10);

        $rows = DB::table('ventas as v')
            ->join('detalle_venta as d', 'd.venta_id', '=', 'v.id')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->leftJoin('proveedores as pr', 'pr.id', '=', 'p.proveedor_id')
            ->whereNull('v.deleted_at')
            ->when($from, fn($q) => $q->whereDate('v.fecha','>=',$from))
            ->when($to,   fn($q) => $q->whereDate('v.fecha','<=',$to))
            ->selectRaw('
                d.producto_id,
                p.codigo,
                p.nombre,
                p.precio_venta,
                p.precio_compra,
                p.estado,
                pr.nombre as proveedor_nombre,
                SUM(d.cantidad) as cantidad_total,
                SUM(d.cantidad * d.precio_unitario) as ingreso_total
            ')
            ->groupBy('d.producto_id','p.codigo','p.nombre','p.precio_venta','p.precio_compra','p.estado','pr.nombre')
            ->orderByDesc('ingreso_total')
            ->limit($limit)
            ->get();

        return response()->json([
            'range' => ['from'=>$from, 'to'=>$to],
            'limit' => $limit,
            'data'  => $rows,
        ], Response::HTTP_OK);
    }

    /**
     * Proveedores (JSON): Lista completa con información de compras, pagos y saldo.
     * Muestra todos los proveedores con datos enriquecidos.
     */
    public function proveedores(Request $request)
    {
        $request->validate([
            'from'   => ['nullable','date'],
            'to'     => ['nullable','date','after_or_equal:from'],
            'limit'  => ['nullable','integer','min:1','max:500'],
            'estado' => ['nullable','in:activo,inactivo'],
        ]);

        $from   = $request->input('from');
        $to     = $request->input('to');
        $limit  = (int) $request->input('limit', 100);
        $estado = $request->input('estado');

        // Obtener todos los proveedores con información completa
        $proveedoresQuery = DB::table('proveedores as pr')
            ->select(
                'pr.id',
                'pr.nombre',
                'pr.cuit',
                'pr.telefono',
                'pr.email',
                'pr.estado',
                'pr.created_at'
            )
            ->whereNull('pr.deleted_at')
            ->when($estado, fn($q) => $q->where('pr.estado', $estado))
            ->orderBy('pr.nombre')
            ->limit($limit)
            ->get();

        $proveedores = $proveedoresQuery->map(function ($proveedor) use ($from, $to) {
            $proveedorId = $proveedor->id;

            // Total compras en el período (solo no anuladas)
            $comprasQuery = DB::table('compras')
                ->where('proveedor_id', $proveedorId)
                ->where('estado', '!=', 'anulado')
                ->when($from, fn($q) => $q->whereDate('fecha_compra', '>=', $from))
                ->when($to, fn($q) => $q->whereDate('fecha_compra', '<=', $to));
            
            $totalCompras = $comprasQuery->sum('monto_total') ?? 0;
            $cantidadCompras = $comprasQuery->count();

            // Total pagos en el período
            $pagosQuery = DB::table('pagos_proveedores')
                ->where('proveedor_id', $proveedorId)
                ->when($from, fn($q) => $q->whereDate('fecha_pago', '>=', $from))
                ->when($to, fn($q) => $q->whereDate('fecha_pago', '<=', $to));
            
            $totalPagos = $pagosQuery->sum('monto') ?? 0;
            $cantidadPagos = $pagosQuery->count();

            // Saldo = Total compras - Total pagos
            $saldo = $totalCompras - $totalPagos;

            // Productos asociados al proveedor (si existe la columna)
            $cantidadProductos = 0;
            if (Schema::hasColumn('productos', 'proveedor_id')) {
                $cantidadProductos = DB::table('productos')
                    ->where('proveedor_id', $proveedorId)
                    ->whereNull('deleted_at')
                    ->count();
            }

            // Ventas de productos del proveedor en el período
            $ingresoVentas = 0;
            $cantidadVendida = 0;
            if (Schema::hasColumn('productos', 'proveedor_id')) {
                $ventasData = DB::table('ventas as v')
                    ->join('detalle_venta as d', 'd.venta_id', '=', 'v.id')
                    ->join('productos as p', 'p.id', '=', 'd.producto_id')
                    ->where('p.proveedor_id', $proveedorId)
                    ->whereNull('v.deleted_at')
                    ->when($from, fn($q) => $q->whereDate('v.fecha', '>=', $from))
                    ->when($to, fn($q) => $q->whereDate('v.fecha', '<=', $to))
                    ->selectRaw('SUM(d.cantidad * d.precio_unitario) as ingreso, SUM(d.cantidad) as cantidad')
                    ->first();
                
                $ingresoVentas = (float) ($ventasData->ingreso ?? 0);
                $cantidadVendida = (float) ($ventasData->cantidad ?? 0);
            }

            return [
                'proveedor_id'      => (int) $proveedor->id,
                'nombre'            => $proveedor->nombre,
                'cuit'              => $proveedor->cuit ?? '-',
                'telefono'          => $proveedor->telefono ?? '-',
                'email'             => $proveedor->email ?? '-',
                'estado'            => $proveedor->estado,
                'cantidad_compras'  => (int) $cantidadCompras,
                'total_compras'     => (float) $totalCompras,
                'cantidad_pagos'    => (int) $cantidadPagos,
                'total_pagos'       => (float) $totalPagos,
                'saldo'             => (float) $saldo,
                'cantidad_productos'=> (int) $cantidadProductos,
                'ingreso_ventas'    => (float) $ingresoVentas,
                'cantidad_vendida'  => (float) $cantidadVendida,
                'fecha_registro'    => $proveedor->created_at,
            ];
        });

        // Calcular totales generales
        $totalComprasGeneral = $proveedores->sum('total_compras');
        $totalIngresoVentasGeneral = $proveedores->sum('ingreso_ventas');
        
        // Calcular participación de cada proveedor basándose en ingreso_ventas
        $proveedores = $proveedores->map(function ($proveedor) use ($totalIngresoVentasGeneral) {
            // Participación = porcentaje de ventas de productos del proveedor sobre el total
            $participacion = $totalIngresoVentasGeneral > 0 
                ? round(($proveedor['ingreso_ventas'] / $totalIngresoVentasGeneral) * 100, 2)
                : 0;
            
            $proveedor['participacion'] = (float) $participacion;
            return $proveedor;
        });

        // Ordenar por participación descendente (mayor participación primero)
        $proveedores = $proveedores->sortByDesc('participacion')->values();

        $totales = [
            'total_proveedores'  => $proveedores->count(),
            'total_compras'      => $totalComprasGeneral,
            'total_pagos'        => $proveedores->sum('total_pagos'),
            'saldo_total'        => $proveedores->sum('saldo'),
            'ingreso_ventas_total' => $totalIngresoVentasGeneral,
        ];

        return response()->json([
            'range'   => ['from' => $from, 'to' => $to],
            'mode'    => 'reporte_completo_proveedores',
            'totales' => $totales,
            'data'    => $proveedores->all(),
        ], Response::HTTP_OK);
    }

    /**
     * EXPORTS
     */

    // Proveedores → XLSX
    public function exportProveedoresXlsx(Request $request)
    {
        $request->validate([
            'from'               => ['nullable','date'],
            'to'                 => ['nullable','date','after_or_equal:from'],
            'include_unassigned' => ['nullable','boolean'],
        ]);

        $from  = $request->input('from');
        $to    = $request->input('to');
        $includeUnassigned = $request->boolean('include_unassigned', true);

        $filename = 'reporte_proveedores_'.($from ?? 'inicio').'_a_'.($to ?? 'hoy').'.xlsx';

        return Excel::download(
            new ProveedoresRankingExport($from, $to, $includeUnassigned),
            $filename
        );
    }

    public function exportProveedoresCsv(Request $request)
    {
        $request->validate([
            'from'               => ['nullable','date'],
            'to'                 => ['nullable','date','after_or_equal:from'],
            'include_unassigned' => ['nullable','boolean'],
        ]);

        $from  = $request->input('from');
        $to    = $request->input('to');
        $includeUnassigned = $request->boolean('include_unassigned', true);

        $filename = 'reporte_proveedores_'.($from ?? 'inicio').'_a_'.($to ?? 'hoy').'.csv';

        return Excel::download(
            new ProveedoresRankingExport($from, $to, $includeUnassigned),
            $filename,
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    // Ventas → CSV
    public function exportVentasCsv(Request $request)
    {
        $request->validate([
            'from'  => ['nullable','date'],
            'to'    => ['nullable','date','after_or_equal:from'],
        ]);

        $from  = $request->input('from');
        $to    = $request->input('to');

        $filename = 'reporte_ventas_'.($from ?? 'inicio').'_a_'.($to ?? 'hoy').'.csv';

        return Excel::download(
            new VentasExport($from, $to),
            $filename,
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    // Clientes (Top) → XLSX
    public function exportClientesXlsx(Request $request)
    {
        $request->validate([
            'from'  => ['nullable','date'],
            'to'    => ['nullable','date','after_or_equal:from'],
            'limit' => ['nullable','integer','min:1','max:100'],
        ]);

        $from  = $request->input('from');
        $to    = $request->input('to');
        $limit = (int) $request->input('limit', 10);

        $filename = 'reporte_clientes_'.($from ?? 'inicio').'_a_'.($to ?? 'hoy').'.xlsx';

        return Excel::download(
            new ClientesRankingExport($from, $to, $limit),
            $filename
        );
    }

    public function exportClientesCsv(Request $request)
    {
        $request->validate([
            'from'  => ['nullable','date'],
            'to'    => ['nullable','date','after_or_equal:from'],
            'limit' => ['nullable','integer','min:1','max:100'],
        ]);

        $from  = $request->input('from');
        $to    = $request->input('to');
        $limit = (int) $request->input('limit', 10);

        $filename = 'reporte_clientes_'.($from ?? 'inicio').'_a_'.($to ?? 'hoy').'.csv';

        return Excel::download(
            new ClientesRankingExport($from, $to, $limit),
            $filename,
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    // Productos (Top) → XLSX
    public function exportProductosXlsx(Request $request)
    {
        $request->validate([
            'from'  => ['nullable','date'],
            'to'    => ['nullable','date','after_or_equal:from'],
            'limit' => ['nullable','integer','min:1','max:100'],
        ]);

        $from  = $request->input('from');
        $to    = $request->input('to');
        $limit = (int) $request->input('limit', 10);

        $filename = 'reporte_productos_'.($from ?? 'inicio').'_a_'.($to ?? 'hoy').'.xlsx';

        return Excel::download(
            new ProductosRankingExport($from, $to, $limit),
            $filename
        );
    }

    public function exportProductosCsv(Request $request)
    {
        $request->validate([
            'from'  => ['nullable','date'],
            'to'    => ['nullable','date','after_or_equal:from'],
            'limit' => ['nullable','integer','min:1','max:100'],
        ]);

        $from  = $request->input('from');
        $to    = $request->input('to');
        $limit = (int) $request->input('limit', 10);

        $filename = 'reporte_productos_'.($from ?? 'inicio').'_a_'.($to ?? 'hoy').'.csv';

        return Excel::download(
            new ProductosRankingExport($from, $to, $limit),
            $filename,
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    // Ventas (KPIs + Series) → XLSX
    public function exportVentasXlsx(Request $request)
    {
        $request->validate([
            'from'        => ['nullable','date'],
            'to'          => ['nullable','date','after_or_equal:from'],
            'group_by'    => ['nullable','in:day,month'],
            'cliente_id'  => ['nullable','integer','min:1'],
            'producto_id' => ['nullable','integer','min:1'],
            'metodo_pago' => ['nullable','string','max:50'],
        ]);

        $from       = $request->input('from');
        $to         = $request->input('to');
        $groupBy    = $request->input('group_by', 'day');
        $clienteId  = $request->input('cliente_id');
        $productoId = $request->input('producto_id');
        $metodoPago = $request->input('metodo_pago');

        $filename = 'reporte_ventas_'.$groupBy.'_'.($from ?? 'inicio').'_a_'.($to ?? 'hoy').'.xlsx';

        return Excel::download(
            new VentasReportExport($from, $to, $groupBy, $clienteId, $productoId, $metodoPago),
            $filename
        );
    }
// Reporte Completo (KPIs + Series + Top Clientes + Top Productos) → XLSX (una sola hoja)
    public function exportFullSingleSheetXlsx(\Illuminate\Http\Request $request)
{
    $request->validate([
        'from'               => ['nullable','date'],
        'to'                 => ['nullable','date','after_or_equal:from'],
        'group_by'           => ['nullable','in:day,month'],
        'limit'              => ['nullable','integer','min:1','max:100'],
        'include_unassigned' => ['nullable','boolean'],
    ]);

    $from  = $request->input('from');
    $to    = $request->input('to');
    $group = $request->input('group_by', 'day');
    $limit = (int)$request->input('limit', 10);
    $incUA = $request->boolean('include_unassigned', true);

    $filename = 'reporte_full_single_'.$group.'_'.($from ?? 'inicio').'_a_'.($to ?? 'hoy').'.xlsx';

    return Excel::download(
        new FullReportSingleSheetExport($from, $to, $group, $limit, $incUA),
        $filename
    );
}
}
