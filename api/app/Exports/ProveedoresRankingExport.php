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
        return [
            'ID', 
            'Nombre', 
            'CUIT', 
            'Teléfono', 
            'Email', 
            'Estado',
            '# Compras', 
            'Total Compras', 
            '# Pagos', 
            'Total Pagos', 
            'Saldo',
            '# Productos',
            'Ingreso Ventas',
            'Cantidad Vendida',
        ];
    }

    public function collection(): Collection
    {
        // Obtener todos los proveedores con información completa
        $proveedores = DB::table('proveedores as pr')
            ->select('pr.id', 'pr.nombre', 'pr.cuit', 'pr.telefono', 'pr.email', 'pr.estado')
            ->whereNull('pr.deleted_at')
            ->orderBy('pr.nombre')
            ->get();

        $rows = $proveedores->map(function ($proveedor) {
            $proveedorId = $proveedor->id;

            // Total compras (no anuladas)
            $comprasQuery = DB::table('compras')
                ->where('proveedor_id', $proveedorId)
                ->where('estado', '!=', 'anulado')
                ->when($this->from, fn($q) => $q->whereDate('fecha_compra', '>=', $this->from))
                ->when($this->to, fn($q) => $q->whereDate('fecha_compra', '<=', $this->to));
            
            $totalCompras = $comprasQuery->sum('monto_total') ?? 0;
            $cantidadCompras = $comprasQuery->count();

            // Total pagos
            $pagosQuery = DB::table('pagos_proveedores')
                ->where('proveedor_id', $proveedorId)
                ->when($this->from, fn($q) => $q->whereDate('fecha_pago', '>=', $this->from))
                ->when($this->to, fn($q) => $q->whereDate('fecha_pago', '<=', $this->to));
            
            $totalPagos = $pagosQuery->sum('monto') ?? 0;
            $cantidadPagos = $pagosQuery->count();

            $saldo = $totalCompras - $totalPagos;

            // Productos asociados
            $cantidadProductos = DB::table('productos')
                ->where('proveedor_id', $proveedorId)
                ->whereNull('deleted_at')
                ->count();

            // Ventas de productos del proveedor
            $ventasData = DB::table('ventas as v')
                ->join('detalle_venta as d', 'd.venta_id', '=', 'v.id')
                ->join('productos as p', 'p.id', '=', 'd.producto_id')
                ->where('p.proveedor_id', $proveedorId)
                ->whereNull('v.deleted_at')
                ->when($this->from, fn($q) => $q->whereDate('v.fecha', '>=', $this->from))
                ->when($this->to, fn($q) => $q->whereDate('v.fecha', '<=', $this->to))
                ->selectRaw('SUM(d.cantidad * d.precio_unitario) as ingreso, SUM(d.cantidad) as cantidad')
                ->first();
            
            $ingresoVentas = (float) ($ventasData->ingreso ?? 0);
            $cantidadVendida = (float) ($ventasData->cantidad ?? 0);

            return [
                $proveedor->id,
                $proveedor->nombre,
                $proveedor->cuit ?? '-',
                $proveedor->telefono ?? '-',
                $proveedor->email ?? '-',
                $proveedor->estado,
                $cantidadCompras,
                $totalCompras,
                $cantidadPagos,
                $totalPagos,
                $saldo,
                $cantidadProductos,
                $ingresoVentas,
                $cantidadVendida,
            ];
        });

        return $rows;
    }
}
