<?php

namespace App\Services\Ventas;

use App\Models\Venta;
use App\Models\Pago;
use App\Models\MetodoPago;
use App\Models\MovimientoCuentaCorriente;
use App\Services\Finanzas\CuentaCorrienteService;
use App\Services\Finanzas\ChequeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio para registrar pagos de ventas específicas
 * 
 * INVARIANTES GARANTIZADOS:
 * - Nunca se puede pagar más de la deuda actual
 * - Los pagos se aplican correctamente a CC si corresponde
 * - El estado_pago de la venta se actualiza automáticamente
 * - Los cheques quedan pendientes hasta su cobro efectivo
 */
class RegistrarPagoVentaService
{
    protected $cuentaCorrienteService;
    protected $chequeService;

    public function __construct(
        CuentaCorrienteService $cuentaCorrienteService,
        ChequeService $chequeService
    ) {
        $this->cuentaCorrienteService = $cuentaCorrienteService;
        $this->chequeService = $chequeService;
    }

    /**
     * Ejecuta el registro de un pago para una venta específica
     * 
     * @param Venta $venta
     * @param array $data {
     *   metodo_pago_id: int,
     *   monto: float,
     *   fecha_pago?: date,
     *   numero_cheque?: string,
     *   fecha_vencimiento?: date,
     *   observaciones_cheque?: string
     * }
     * @return Pago
     * @throws ValidationException
     */
    public function ejecutar(Venta $venta, array $data): Pago
    {
        return DB::transaction(function () use ($venta, $data) {
            
            // 1. Calcular deuda actual de la venta
            $deudaActual = $this->calcularDeudaActual($venta);
            
            $monto = round((float)$data['monto'], 2);
            
            // 2. VALIDACIÓN: No pagar más de la deuda
            if ($monto > $deudaActual + 0.01) {
                throw ValidationException::withMessages([
                    'monto' => sprintf(
                        'El monto ($%s) excede la deuda actual de la venta ($%s)',
                        number_format($monto, 2),
                        number_format($deudaActual, 2)
                    )
                ]);
            }
            
            if ($monto <= 0) {
                throw ValidationException::withMessages([
                    'monto' => 'El monto debe ser mayor a cero'
                ]);
            }
            
            // 3. Crear pago
            $pago = Pago::create([
                'venta_id' => $venta->id,
                'metodo_pago_id' => $data['metodo_pago_id'],
                'monto' => $monto,
                'fecha_pago' => $data['fecha_pago'] ?? now(),
            ]);
            
            $metodoPago = MetodoPago::find($data['metodo_pago_id']);
            
            // 4. Si es cheque, registrar en tabla cheques
            if ($metodoPago && strtolower($metodoPago->nombre) === 'cheque') {
                $this->chequeService->registrarChequeDesdeVenta($venta, [
                    'pago_id' => $pago->id,
                    'monto' => $monto,
                    'numero_cheque' => $data['numero_cheque'] ?? null,
                    'fecha_cheque' => $data['fecha_cheque'] ?? now(),
                    // Aceptar fecha_cobro (frontend) o fecha_vencimiento (backend)
                    'fecha_vencimiento' => $data['fecha_vencimiento'] ?? $data['fecha_cobro'] ?? null,
                    'observaciones' => $data['observaciones_cheque'] ?? null,
                ]);
            }
            
            // 5. Aplicar pago a cuenta corriente si la venta tiene deuda en CC
            if ($this->debeAplicarseACC($venta)) {
                // Registrar como pago en CC (reduce deuda)
                $this->cuentaCorrienteService->registrarPagoPorCheque(
                    clienteId: $venta->cliente_id,
                    ventaId: $venta->id,
                    monto: $monto,
                    fecha: $pago->fecha_pago ?? now(),
                    observaciones: "Pago adicional de Venta #{$venta->id}"
                );
            }
            
            // 6. Actualizar estado de la venta
            $venta->refresh();
            $venta->estado_pago = $this->determinarEstadoPago($venta);
            $venta->save();
            
            // Refrescar cliente para actualizar saldo
            $venta->cliente->saldo_actual = $this->cuentaCorrienteService->obtenerSaldoActual($venta->cliente);
            $venta->cliente->save();
            
            return $pago->fresh();
        });
    }

    /**
     * Calcula la deuda actual de una venta específica
     * (total - pagos efectivos realizados)
     */
    protected function calcularDeudaActual(Venta $venta): float
    {
        $total = (float)$venta->total;
        $cuentaCorrienteId = $this->getCuentaCorrienteId();
        
        // Sumar solo pagos reales (no CC)
        $totalPagado = (float)$venta->pagos()
            ->where('metodo_pago_id', '!=', $cuentaCorrienteId)
            ->sum('monto');
        
        return max(0, round($total - $totalPagado, 2));
    }

    /**
     * Determina si el pago debe aplicarse a Cuenta Corriente
     * (verifica si la venta tiene deuda registrada en CC)
     */
    protected function debeAplicarseACC(Venta $venta): bool
    {
        // Verificar si hay movimiento de deuda en CC para esta venta
        return MovimientoCuentaCorriente::where('venta_id', $venta->id)
            ->where('tipo', 'venta')
            ->exists();
    }

    /**
     * Determina el estado de pago de la venta
     */
    protected function determinarEstadoPago(Venta $venta): string
    {
        $deuda = $this->calcularDeudaActual($venta);
        
        // Tolerancia de 1 centavo
        if ($deuda <= 0.01) {
            return 'pagado';
        }
        
        $tienePagos = $venta->pagos()
            ->where('metodo_pago_id', '!=', $this->getCuentaCorrienteId())
            ->exists();
        
        if ($tienePagos) {
            return 'parcial';
        }
        
        return 'pendiente';
    }

    /**
     * Obtiene el ID del método de pago Cuenta Corriente
     */
    protected function getCuentaCorrienteId(): ?int
    {
        static $cuentaCorrienteId = null;
        
        if ($cuentaCorrienteId === null) {
            $cuentaCorrienteId = MetodoPago::where('nombre', 'Cuenta Corriente')->value('id');
        }
        
        return $cuentaCorrienteId;
    }
}
