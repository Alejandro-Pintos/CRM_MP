<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ProductosTopExport implements FromCollection, WithHeadings, WithColumnFormatting, ShouldAutoSize
{
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected int $limit = 50
    ) {}

    public function collection(): Collection
    {
        $rows = DB::table('ventas as v')
            ->join('detalle_venta as d', 'd.venta_id', '=', 'v.id')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->when($this->from, fn($q) => $q->whereDate('v.fecha','>=',$this->from))
            ->when($this->to,   fn($q) => $q->whereDate('v.fecha','<=',$this->to))
            ->selectRaw("
                d.producto_id,
                p.nombre,
                SUM(d.cantidad)                     as cantidad_total,
                SUM(d.cantidad * d.precio_unitario) as ingreso_total
            ")
            ->groupBy('d.producto_id','p.nombre')
            ->orderByDesc('ingreso_total')
            ->limit($this->limit)
            ->get();

        return collect($rows)->map(fn($r) => [
            (int) $r->producto_id,
            (string) $r->nombre,
            (float) $r->cantidad_total,
            (float) $r->ingreso_total,
        ]);
    }

    public function headings(): array
    {
        return ['producto_id', 'nombre', 'cantidad_total', 'ingreso_total'];
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_NUMBER_00,
            'D' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }
}
