<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class VentasReportExport implements WithMultipleSheets
{
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected string $groupBy = 'day',
        protected ?int $clienteId = null,
        protected ?int $productoId = null,
        protected ?string $metodoPago = null
    ) {}

    public function sheets(): array
    {
        return [
            new VentasKpisSheet($this->from, $this->to, $this->clienteId, $this->productoId, $this->metodoPago),
            new VentasSeriesSheet($this->from, $this->to, $this->groupBy, $this->clienteId, $this->productoId, $this->metodoPago),
        ];
    }
}
