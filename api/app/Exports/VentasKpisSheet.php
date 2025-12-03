<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class VentasKpisSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
{
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected ?int $clienteId = null,
        protected ?int $productoId = null,
        protected ?string $metodoPago = null
    ) {}

    public function title(): string
    {
        return 'KPIs';
    }

    public function collection(): Collection
    {
        $base = DB::table('ventas as v')
            ->whereNull('v.deleted_at') // Excluir ventas eliminadas (soft delete)
            ->when($this->from, fn($q) => $q->whereDate('v.fecha','>=',$this->from))
            ->when($this->to,   fn($q) => $q->whereDate('v.fecha','<=',$this->to))
            ->when($this->clienteId,  fn($q) => $q->where('v.cliente_id', $this->clienteId))
            ->when($this->productoId, function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->from('detalle_venta as d')
                        ->whereColumn('d.venta_id', 'v.id')
                        ->where('d.producto_id', $this->productoId);
                });
            })
            ->when($this->metodoPago, function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->from('pagos as pg')
                        ->whereColumn('pg.venta_id', 'v.id')
                        ->where('pg.method', $this->metodoPago);
                });
            });

        $row = $base->selectRaw("COALESCE(SUM(v.total),0) as total, COUNT(*) as cnt")->first();
        $total = (float) ($row->total ?? 0);
        $cnt   = (int)   ($row->cnt ?? 0);
        $ticket = $cnt > 0 ? round($total / $cnt, 2) : 0.0;

        // Devolvemos tabla simple clave/valor
        return collect([
            ['total_neto', $total],
            ['ventas_count', $cnt],
            ['ticket_promedio', $ticket],
        ]);
    }

    public function headings(): array
    {
        return ['kpi', 'valor'];
    }
}
