<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class FullReportSingleSheetExport implements FromView, WithTitle, ShouldAutoSize
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public string $groupBy = 'day',     // 'day' | 'month'
        public int $limit = 10,
        public bool $includeUnassigned = true // incluir fila “Sin proveedor”
    ) {}

    public function title(): string
    {
        return 'Reporte Completo';
    }

    public function view(): View
    {
        // ---- Ventas base
        $ventasBase = DB::table('ventas')->whereNull('deleted_at'); // Excluir eliminadas
        if ($this->from) $ventasBase->whereDate('fecha', '>=', $this->from);
        if ($this->to)   $ventasBase->whereDate('fecha', '<=', $this->to);

        // KPIs
        $k = (clone $ventasBase)
            ->selectRaw('COALESCE(SUM(total),0) as total, COUNT(*) as cnt')
            ->first();

        $totalVentas  = (float)($k->total ?? 0);
        $ventasCount  = (int)($k->cnt ?? 0);
        $ticketProm   = $ventasCount > 0 ? round($totalVentas / $ventasCount, 2) : 0.0;

        // Serie temporal
        if ($this->groupBy === 'month') {
            $serie = (clone $ventasBase)
                ->selectRaw("DATE_FORMAT(fecha, '%Y-%m-01') as period, COALESCE(SUM(total),0) as total, COUNT(*) as cnt")
                ->groupBy('period')->orderBy('period')->get();
        } else {
            $serie = (clone $ventasBase)
                ->selectRaw("DATE(fecha) as period, COALESCE(SUM(total),0) as total, COUNT(*) as cnt")
                ->groupBy('period')->orderBy('period')->get();
        }

        // TOP Clientes
        $clientes = DB::table('ventas as v')
            ->join('clientes as c', 'c.id', '=', 'v.cliente_id')
            ->whereNull('v.deleted_at') // Excluir ventas eliminadas
            ->when($this->from, fn($q) => $q->whereDate('v.fecha','>=',$this->from))
            ->when($this->to,   fn($q) => $q->whereDate('v.fecha','<=',$this->to))
            ->selectRaw('v.cliente_id, c.nombre, COUNT(*) as compras, SUM(v.total) as ingreso_total')
            ->groupBy('v.cliente_id','c.nombre')
            ->orderByDesc('ingreso_total')
            ->limit($this->limit)
            ->get();

        // TOP Productos
        $productos = DB::table('ventas as v')
            ->join('detalle_venta as d', 'd.venta_id', '=', 'v.id')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->whereNull('v.deleted_at') // Excluir ventas eliminadas
            ->when($this->from, fn($q) => $q->whereDate('v.fecha','>=',$this->from))
            ->when($this->to,   fn($q) => $q->whereDate('v.fecha','<=',$this->to))
            ->selectRaw('d.producto_id, p.nombre,
                         SUM(d.cantidad) as cantidad_total,
                         SUM(d.cantidad * d.precio_unitario) as ingreso_total')
            ->groupBy('d.producto_id','p.nombre')
            ->orderByDesc('ingreso_total')
            ->limit($this->limit)
            ->get();

        // TOP Proveedores (con “Sin proveedor” si corresponde)
        $proveedores = collect();
        $totalIngresosProveedores = 0.0;

        if (Schema::hasColumn('productos', 'proveedor_id')) {
            $base = DB::table('ventas as v')
                ->join('detalle_venta as d', 'd.venta_id', '=', 'v.id')
                ->join('productos as p', 'p.id', '=', 'd.producto_id')
                ->leftJoin('proveedores as pr', 'pr.id', '=', 'p.proveedor_id')
                ->whereNull('v.deleted_at') // Excluir ventas eliminadas
                ->when($this->from, fn($q) => $q->whereDate('v.fecha','>=',$this->from))
                ->when($this->to,   fn($q) => $q->whereDate('v.fecha','<=',$this->to));

            if (!$this->includeUnassigned) {
                $base->whereNotNull('p.proveedor_id');
            }

            $proveedores = $base
                ->selectRaw("
                    COALESCE(pr.id, 0)                   as proveedor_id,
                    COALESCE(pr.nombre, 'Sin proveedor') as nombre,
                    SUM(d.cantidad)                      as cantidad_total,
                    SUM(d.cantidad * d.precio_unitario)  as ingreso_total
                ")
                ->groupBy('proveedor_id','nombre')
                ->orderByRaw("CASE WHEN proveedor_id = 0 THEN 1 ELSE 0 END, ingreso_total DESC")
                ->limit($this->limit)
                ->get();

            $totalIngresosProveedores = (float) $proveedores->sum(fn($r) => (float) $r->ingreso_total);
        }

        return view('exports.full_report_single', [
            'from'        => $this->from,
            'to'          => $this->to,
            'groupBy'     => $this->groupBy,
            'limit'       => $this->limit,
            'includeUA'   => $this->includeUnassigned,

            'kpis' => [
                'total_neto'      => $totalVentas,
                'ventas_count'    => $ventasCount,
                'ticket_promedio' => $ticketProm,
            ],
            'serie'    => $serie,
            'clientes' => $clientes,
            'productos'=> $productos,
            'proveedores' => $proveedores,
            'totalIngresosProveedores' => $totalIngresosProveedores,
            'tieneProveedorId' => Schema::hasColumn('productos','proveedor_id'),
        ]);
    }
}
