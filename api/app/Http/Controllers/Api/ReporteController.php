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

        // Series
        if ($groupBy === 'month') {
            $series = (clone $q)
                ->selectRaw("DATE_FORMAT(fecha, '%Y-%m-01') as period, COALESCE(SUM($TOTAL_COL),0) as total, COUNT(*) as cnt")
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->map(fn($r) => [
                    'period'        => $r->period,
                    'total_neto'    => (float) $r->total,
                    'ventas_count'  => (int)   $r->cnt,
                ])->all();
        } else {
            $series = (clone $q)
                ->selectRaw("DATE(fecha) as period, COALESCE(SUM($TOTAL_COL),0) as total, COUNT(*) as cnt")
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->map(fn($r) => [
                    'period'        => $r->period,
                    'total_neto'    => (float) $r->total,
                    'ventas_count'  => (int)   $r->cnt,
                ])->all();
        }

        return response()->json([
            'range' => ['from'=>$from, 'to'=>$to, 'tz'=>$tz],
            'filters' => [
                'cliente_id'  => $clienteId,
                'producto_id' => $productoId,
                'metodo_pago' => $metodoPago,
                'group_by'    => $groupBy,
            ],
            'kpis' => [
                'total_neto'      => $total,
                'ventas_count'    => $cnt,
                'ticket_promedio' => $ticketProm,
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
            ->when($from, fn($q) => $q->whereDate('v.fecha','>=',$from))
            ->when($to,   fn($q) => $q->whereDate('v.fecha','<=',$to))
            ->selectRaw('v.cliente_id, c.nombre, COUNT(*) as compras, SUM(v.total) as ingreso_total')
            ->groupBy('v.cliente_id','c.nombre')
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
            ->when($from, fn($q) => $q->whereDate('v.fecha','>=',$from))
            ->when($to,   fn($q) => $q->whereDate('v.fecha','<=',$to))
            ->selectRaw('
                d.producto_id,
                p.nombre,
                SUM(d.cantidad)                      as cantidad_total,
                SUM(d.cantidad * d.precio_unitario)  as ingreso_total
            ')
            ->groupBy('d.producto_id','p.nombre')
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
     * Proveedores (JSON): ranking por ventas si existe productos.proveedor_id,
     * si no, devuelve resumen básico.
     * Soporta include_unassigned para agrupar como "Sin proveedor".
     */
    public function proveedores(Request $request)
    {
        $request->validate([
            'from'               => ['nullable','date'],
            'to'                 => ['nullable','date','after_or_equal:from'],
            'limit'              => ['nullable','integer','min:1','max:100'],
            'include_unassigned' => ['nullable','boolean'],
        ]);

        $from  = $request->input('from');
        $to    = $request->input('to');
        $limit = (int) $request->input('limit', 10);
        $includeUnassigned = $request->boolean('include_unassigned', true);

        if (Schema::hasColumn('productos', 'proveedor_id')) {
            $base = DB::table('ventas as v')
                ->join('detalle_venta as d', 'd.venta_id', '=', 'v.id')
                ->join('productos as p', 'p.id', '=', 'd.producto_id')
                ->leftJoin('proveedores as pr', 'pr.id', '=', 'p.proveedor_id')
                ->when($from, fn($q) => $q->whereDate('v.fecha','>=',$from))
                ->when($to,   fn($q) => $q->whereDate('v.fecha','<=',$to))
                ->when(!$includeUnassigned, fn($q) => $q->whereNotNull('p.proveedor_id'));

            $rows = $base
                ->selectRaw("
                    COALESCE(pr.id, 0)                   as proveedor_id,
                    COALESCE(pr.nombre, 'Sin proveedor') as nombre,
                    SUM(d.cantidad)                      as cantidad_total,
                    SUM(d.cantidad * d.precio_unitario)  as ingreso_total
                ")
                ->groupBy('pr.id', 'pr.nombre')
                ->orderByRaw("CASE WHEN COALESCE(pr.id, 0) = 0 THEN 1 ELSE 0 END, ingreso_total DESC")
                ->limit($limit)
                ->get();

            $totalIngresos = $rows->sum(fn($r) => (float) $r->ingreso_total);

            $rows = $rows->map(function ($r) use ($totalIngresos) {
                $ingreso = (float) $r->ingreso_total;
                return [
                    'proveedor_id'   => (int) $r->proveedor_id,  // 0 = Sin proveedor
                    'nombre'         => (string) $r->nombre,
                    'cantidad_total' => (float) $r->cantidad_total,
                    'ingreso_total'  => $ingreso,
                    'participacion'  => $totalIngresos > 0 ? round($ingreso * 100 / $totalIngresos, 2) : 0.0,
                ];
            });

            return response()->json([
                'range'          => ['from'=>$from, 'to'=>$to],
                'limit'          => $limit,
                'mode'           => $includeUnassigned
                                    ? 'ranking_por_ventas_incluye_sin_proveedor'
                                    : 'ranking_por_ventas_solo_asignados',
                'total_ingresos' => $totalIngresos,
                'data'           => $rows,
            ], Response::HTTP_OK);
        }

        // Fallback: resumen simple
        $totalProveedores = DB::table('proveedores')->count();
        $porEstado = [];
        if (Schema::hasColumn('proveedores', 'estado')) {
            $porEstado = DB::table('proveedores')
                ->selectRaw('estado, COUNT(*) as cantidad')
                ->groupBy('estado')
                ->get();
        }

        return response()->json([
            'range'      => ['from'=>$from, 'to'=>$to],
            'mode'       => 'resumen_proveedores',
            'totales'    => ['proveedores' => $totalProveedores],
            'por_estado' => $porEstado,
            'nota'       => 'No existe productos.proveedor_id; se devuelve resumen básico.',
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
