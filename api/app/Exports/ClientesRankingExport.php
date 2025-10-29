<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClientesRankingExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    private ?string $from;
    private ?string $to;
    private ?int $limit;

    public function __construct(?string $from = null, ?string $to = null, ?int $limit = null)
    {
        $this->from = $from;
        $this->to = $to;
        $this->limit = $limit;
    }

    public function headings(): array
    {
        return ['cliente_id', 'nombre', 'email', 'telefono', 'cantidad_ventas', 'total_comprado', 'participacion_%'];
    }

    public function collection(): Collection
    {
        $query = DB::table('ventas as v')
            ->join('clientes as c', 'c.id', '=', 'v.cliente_id')
            ->when($this->from, fn($q) => $q->whereDate('v.fecha', '>=', $this->from))
            ->when($this->to,   fn($q) => $q->whereDate('v.fecha', '<=', $this->to));

        $rows = $query->selectRaw("
                c.id                   as cliente_id,
                c.nombre               as nombre,
                c.email                as email,
                c.telefono             as telefono,
                COUNT(v.id)            as cantidad_ventas,
                SUM(v.total)           as total_comprado
            ")
            ->groupBy('c.id', 'c.nombre', 'c.email', 'c.telefono')
            ->orderByDesc('total_comprado')
            ->when($this->limit, fn($q) => $q->limit($this->limit))
            ->get();

        $total = (float) $rows->sum('total_comprado');

        return $rows->map(function ($r) use ($total) {
            $comprado = (float) $r->total_comprado;
            $part     = $total > 0 ? round($comprado * 100 / $total, 2) : 0.0;

            return collect([
                (int)    $r->cliente_id,
                (string) $r->nombre,
                (string) ($r->email ?? ''),
                (string) ($r->telefono ?? ''),
                (int)    $r->cantidad_ventas,
                (float)  $comprado,
                (float)  $part,
            ]);
        });
    }
}
