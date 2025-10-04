<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class VentasSeriesSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
{
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected string $groupBy = 'day', // day|month
        protected ?int $clienteId = null,
        protected ?int $productoId = null,
        protected ?string $metodoPago = null
    ) {}

    public function title(): string
    {
        return 'Series_'.$this->groupBy;
    }

    public function collection(): Collection
    {
        $periodExpr = $this->groupBy === 'month'
            ? "DATE_FORMAT(v.fecha, '%Y-%m-01')"
            : "DATE(v.fecha)";

        $base = DB::table('ventas as v')
            ->when($this->from, fn($q) => $q->whereDate('v.fecha','>=',$this->from))
            ->when($this->to,   fn($q) => $q->whereDate('v.fecha','<=',$this->to))
            ->when($this->clienteId,  fn($q) => $q->where('v.cliente_id', $this->clienteId))
            // producto: evitamos duplicaciones usando EXISTS en lugar de join
            ->when($this->productoId, function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->from('detalle_venta as d')
                        ->whereColumn('d.venta_id', 'v.id')
                        ->where('d.producto_id', $this->productoId);
                });
            })
            // método de pago: también con EXISTS
            ->when($this->metodoPago, function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->from('pagos as pg')
                        ->whereColumn('pg.venta_id', 'v.id')
                        ->where('pg.method', $this->metodoPago);
                });
            });

        $series = $base
            ->selectRaw("$periodExpr as period, COALESCE(SUM(v.total),0) as total, COUNT(*) as cnt")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return collect($series)->map(fn($r) => [
            (string) $r->period,
            (float)  $r->total,
            (int)    $r->cnt,
        ]);
    }

    public function headings(): array
    {
        return ['period', 'total_neto', 'ventas_count'];
    }
}
