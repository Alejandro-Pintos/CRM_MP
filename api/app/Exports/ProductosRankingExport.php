<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ProductosRankingExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    private ?string $from;
    private ?string $to;
    private int $limit;

    public function __construct(?string $from = null, ?string $to = null, int $limit = 10)
    {
        $this->from  = $from;
        $this->to    = $to;
        $this->limit = $limit;
    }

    public function headings(): array
    {
        return ['producto_id', 'nombre', 'cantidad_total', 'ingreso_total'];
    }

    public function collection(): Collection
    {
        $rows = DB::table('ventas as v')
            ->join('detalle_venta as d', 'd.venta_id', '=', 'v.id')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->whereNull('v.deleted_at') // Excluir ventas eliminadas (soft delete)
            ->when($this->from, fn($q) => $q->whereDate('v.fecha', '>=', $this->from))
            ->when($this->to,   fn($q) => $q->whereDate('v.fecha', '<=', $this->to))
            ->selectRaw("
                d.producto_id as producto_id,
                p.nombre      as nombre,
                SUM(d.cantidad)                     as cantidad_total,
                SUM(d.cantidad * d.precio_unitario) as ingreso_total
            ")
            ->groupBy('producto_id', 'nombre')
            ->orderByDesc('ingreso_total')
            ->limit($this->limit)
            ->get()
            ->map(fn($r) => collect([
                (int)   $r->producto_id,
                (string)$r->nombre,
                (float) $r->cantidad_total,
                (float) $r->ingreso_total,
            ]));

        return collect($rows);
    }
}
