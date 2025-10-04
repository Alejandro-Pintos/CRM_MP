<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProveedoresRankingExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    private ?string $from;
    private ?string $to;
    private bool $includeUnassigned;

    public function __construct(?string $from = null, ?string $to = null, bool $includeUnassigned = true)
    {
        $this->from = $from;
        $this->to = $to;
        $this->includeUnassigned = $includeUnassigned;
    }

    public function headings(): array
    {
        return ['proveedor_id', 'nombre', 'cantidad_total', 'ingreso_total', 'participacion_%'];
    }

    public function collection(): Collection
    {
        $base = DB::table('ventas as v')
            ->join('detalle_venta as d', 'd.venta_id', '=', 'v.id')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->leftJoin('proveedores as pr', 'pr.id', '=', 'p.proveedor_id')
            ->when($this->from, fn($q) => $q->whereDate('v.fecha', '>=', $this->from))
            ->when($this->to,   fn($q) => $q->whereDate('v.fecha', '<=', $this->to))
            ->when(!$this->includeUnassigned, fn($q) => $q->whereNotNull('p.proveedor_id'));

        $rows = $base->selectRaw("
                COALESCE(pr.id, 0)                   as proveedor_id,
                COALESCE(pr.nombre, 'Sin proveedor') as nombre,
                SUM(d.cantidad)                      as cantidad_total,
                SUM(d.cantidad * d.precio_unitario)  as ingreso_total
            ")
            ->groupBy('proveedor_id', 'nombre')
            ->orderByRaw("CASE WHEN proveedor_id = 0 THEN 1 ELSE 0 END, ingreso_total DESC")
            ->get();

        $total = (float) $rows->sum('ingreso_total');

        return $rows->map(function ($r) use ($total) {
            $ingreso = (float) $r->ingreso_total;
            $part    = $total > 0 ? round($ingreso * 100 / $total, 2) : 0.0;

            return collect([
                (int)   $r->proveedor_id,
                (string)$r->nombre,
                (float) $r->cantidad_total,
                (float) $ingreso,
                (float) $part,
            ]);
        });
    }
}
