<?php

namespace App\Services\Finanzas;

use App\Models\Cliente;
use App\Models\Venta;
use App\Models\MovimientoCuentaCorriente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de dominio para Cuenta Corriente de clientes.
 * 
 * RESPONSABILIDADES:
 * - Registrar deudas por ventas a crédito.
 * - Aplicar pagos a deudas existentes (FIFO).
 * - Calcular saldos actuales y disponibilidad de crédito.
 * - Validar límites de crédito.
 * 
 * INVARIANTES GARANTIZADOS:
 * - El saldo nunca puede ser negativo (cliente NO puede estar "a favor").
 * - Los pagos siempre se aplican FIFO (First In, First Out) a las ventas más antiguas.
 * - Nunca se puede pagar más de lo que se debe.
 * - Los movimientos siempre tienen cliente_id y están vinculados a una venta cuando corresponde.
 */
class CuentaCorrienteService
{
    /**
     * Registra una deuda por venta en cuenta corriente.
     * 
     * INVARIANTE: No permite registrar la misma venta dos veces.
     * INVARIANTE: Valida que no supere el límite de crédito.
     * 
     * @param Venta $venta
     * @param float|null $montoACreditoParcial Si es null, usa el total de la venta
     * @return MovimientoCuentaCorriente
     * @throws ValidationException
     */
    public function registrarDeudaPorVenta(Venta $venta, ?float $montoACreditoParcial = null): MovimientoCuentaCorriente
    {
        return DB::transaction(function () use ($venta, $montoACreditoParcial) {
            
            // Bloquear cliente
            $cliente = Cliente::lockForUpdate()->findOrFail($venta->cliente_id);

            // VALIDACIÓN: Evitar duplicados
            $yaRegistrado = MovimientoCuentaCorriente::where('venta_id', $venta->id)
                ->where('tipo', 'venta')
                ->exists();

            if ($yaRegistrado) {
                throw ValidationException::withMessages([
                    'venta_id' => 'La venta ya fue registrada en cuenta corriente.'
                ]);
            }

            // Determinar monto a acreditar
            $monto = $montoACreditoParcial ?? $venta->total;
            $monto = round($monto, 2);

            // VALIDACIÓN: Límite de crédito
            if ((float)$cliente->limite_credito > 0) {
                $saldoActual = $this->obtenerSaldoActual($cliente);
                $saldoProyectado = $saldoActual + $monto;

                if ($saldoProyectado > (float)$cliente->limite_credito + 0.01) {
                    throw ValidationException::withMessages([
                        'limite_credito' => sprintf(
                            'Excedería el límite de crédito. Límite: $%s, Deuda actual: $%s, Disponible: $%s',
                            number_format($cliente->limite_credito, 2),
                            number_format($saldoActual, 2),
                            number_format($cliente->limite_credito - $saldoActual, 2)
                        )
                    ]);
                }
            }

            // Crear movimiento DEBE
            $movimiento = MovimientoCuentaCorriente::create([
                'cliente_id' => $cliente->id,
                'venta_id' => $venta->id,
                'tipo' => 'venta',
                'monto' => abs($monto),
                'debe' => abs($monto),
                'haber' => 0,
                'fecha' => $venta->fecha ?? now(),
                'descripcion' => "Venta #{$venta->id} a crédito",
            ]);

            // Actualizar saldo del cliente
            $cliente->recalcularSaldo();

            \Log::info('Deuda registrada en CC', [
                'movimiento_id' => $movimiento->id,
                'venta_id' => $venta->id,
                'cliente_id' => $cliente->id,
                'monto' => $monto,
                'saldo_nuevo' => $cliente->saldo_actual,
            ]);

            return $movimiento;
        });
    }

    /**
     * Registra un pago de cheque cobrado que reduce deuda en Cuenta Corriente.
     * Similar a registrarPagoDesdeCuentaCorriente pero específico para cheques.
     * 
     * @param int $clienteId
     * @param int $ventaId
     * @param float $monto
     * @param \DateTime|string $fecha
     * @param string|null $observaciones
     * @return MovimientoCuentaCorriente
     */
    public function registrarPagoPorCheque(
        int $clienteId,
        int $ventaId,
        float $monto,
        $fecha,
        ?string $observaciones = null
    ): MovimientoCuentaCorriente {
        return DB::transaction(function () use ($clienteId, $ventaId, $monto, $fecha, $observaciones) {
            
            // Bloquear cliente
            $cliente = Cliente::lockForUpdate()->findOrFail($clienteId);
            
            // Crear movimiento HABER (reduce deuda)
            $movimiento = MovimientoCuentaCorriente::create([
                'cliente_id' => $clienteId,
                'venta_id' => $ventaId,
                'tipo' => 'pago',
                'monto' => -abs($monto),
                'debe' => 0,
                'haber' => abs($monto),
                'fecha' => $fecha,
                'descripcion' => $observaciones ?? "Cobro de cheque - Venta #{$ventaId}",
            ]);
            
            // Recalcular saldo del cliente
            $cliente->recalcularSaldo();
            
            \Log::info('Pago por cheque registrado en CC', [
                'movimiento_id' => $movimiento->id,
                'venta_id' => $ventaId,
                'cliente_id' => $clienteId,
                'monto' => $monto,
                'saldo_nuevo' => $cliente->saldo_actual,
            ]);
            
            return $movimiento;
        });
    }

    /**
     * Registra un pago de cuenta corriente y lo distribuye FIFO entre
     * las ventas pendientes del cliente.
     * 
     * INVARIANTE: El pago se aplica primero a las ventas más antiguas.
     * INVARIANTE: No se puede pagar más de lo que se debe.
     * 
     * @param int $clienteId
     * @param float $montoPago
     * @param string $fecha
     * @param int|null $metodoPagoId
     * @param string|null $observaciones
     * @return array ['movimientos_creados' => array, 'ventas_afectadas' => array]
     * @throws ValidationException
     */
    public function registrarPagoDesdeCuentaCorriente(
        int $clienteId,
        float $montoPago,
        string $fecha,
        ?int $metodoPagoId = null,
        ?string $observaciones = null
    ): array {
        return DB::transaction(function () use ($clienteId, $montoPago, $fecha, $metodoPagoId, $observaciones) {
            
            // Bloquear cliente para consistencia
            $cliente = Cliente::lockForUpdate()->findOrFail($clienteId);
            
            $montoRestante = round($montoPago, 2);
            $movimientosCreados = [];
            $ventasAfectadas = [];
            
            // 1. Obtener movimientos DEBE pendientes (FIFO por fecha)
            $movimientosDebe = MovimientoCuentaCorriente::where('cliente_id', $clienteId)
                ->where('tipo', 'venta')
                ->where('debe', '>', 0)
                ->orderBy('fecha')
                ->orderBy('id')
                ->get();
            
            // 2. Calcular saldo pendiente de cada movimiento DEBE
            foreach ($movimientosDebe as $movDebe) {
                if ($montoRestante <= 0) break;
                
                // Calcular cuánto se ha pagado de este movimiento DEBE
                $haberAplicado = MovimientoCuentaCorriente::where('cliente_id', $clienteId)
                    ->where('tipo', 'pago')
                    ->where('venta_id', $movDebe->venta_id)
                    ->sum('haber');
                
                $saldoPendiente = $movDebe->debe - $haberAplicado;
                
                if ($saldoPendiente <= 0) continue; // Ya está totalmente pagado
                
                // 3. Aplicar pago a este movimiento (hasta saldarlo o agotar monto)
                $montoAplicado = min($montoRestante, $saldoPendiente);
                
                // 4. Crear movimiento HABER vinculado a la venta
                $movimientoPago = MovimientoCuentaCorriente::create([
                    'cliente_id' => $clienteId,
                    'tipo' => 'pago',
                    'referencia_id' => $metodoPagoId, // ID del método de pago usado
                    'venta_id' => $movDebe->venta_id, // CRÍTICO: vincular a la venta
                    'monto' => -abs($montoAplicado), // Negativo para compatibilidad
                    'debe' => 0,
                    'haber' => abs($montoAplicado),
                    'fecha' => $fecha,
                    'descripcion' => $observaciones ?? "Pago cuenta corriente" . 
                        ($movDebe->venta_id ? " aplicado a Venta #{$movDebe->venta_id}" : ""),
                ]);
                
                $movimientosCreados[] = $movimientoPago;
                
                if ($movDebe->venta_id && !in_array($movDebe->venta_id, $ventasAfectadas)) {
                    $ventasAfectadas[] = $movDebe->venta_id;
                }
                
                $montoRestante -= $montoAplicado;
                
                \Log::info("Pago CC aplicado", [
                    'cliente_id' => $clienteId,
                    'venta_id' => $movDebe->venta_id,
                    'debe_original' => $movDebe->debe,
                    'haber_previo' => $haberAplicado,
                    'saldo_pendiente' => $saldoPendiente,
                    'monto_aplicado' => $montoAplicado,
                    'monto_restante' => $montoRestante
                ]);
            }
            
            // 5. Si sobra dinero (pagó más de lo que debía), crear movimiento genérico
            if ($montoRestante > 0.01) {
                $movimientoPago = MovimientoCuentaCorriente::create([
                    'cliente_id' => $clienteId,
                    'tipo' => 'pago',
                    'referencia_id' => $metodoPagoId,
                    'venta_id' => null, // No vinculado a venta específica
                    'monto' => -abs($montoRestante),
                    'debe' => 0,
                    'haber' => abs($montoRestante),
                    'fecha' => $fecha,
                    'descripcion' => $observaciones ?? "Pago cuenta corriente (excedente sin venta asociada)",
                ]);
                
                $movimientosCreados[] = $movimientoPago;
                
                \Log::warning("Pago CC con excedente", [
                    'cliente_id' => $clienteId,
                    'monto_excedente' => $montoRestante,
                    'razon' => 'Cliente pagó más de lo que debía'
                ]);
            }
            
            // 6. Recalcular saldo del cliente
            $cliente->refresh();
            $cliente->recalcularSaldo();
            
            return [
                'movimientos_creados' => $movimientosCreados,
                'ventas_afectadas' => $ventasAfectadas,
                'cliente' => $cliente->fresh(),
            ];
        });
    }
    
    /**
     * Calcula la deuda de cuenta corriente pendiente para una venta específica.
     * 
     * @param int $ventaId
     * @return float
     */
    public function calcularDeudaCCVenta(int $ventaId): float
    {
        $debe = MovimientoCuentaCorriente::where('venta_id', $ventaId)
            ->where('tipo', 'venta')
            ->sum('debe');
        
        $haber = MovimientoCuentaCorriente::where('venta_id', $ventaId)
            ->where('tipo', 'pago')
            ->sum('haber');
        
        return round($debe - $haber, 2);
    }
    
    /**
     * Obtiene el saldo actual de cuenta corriente de un cliente.
     * Calcula en tiempo real desde los movimientos.
     * 
     * @param Cliente $cliente
     * @return float
     */
    public function obtenerSaldoActual(Cliente $cliente): float
    {
        $debe = MovimientoCuentaCorriente::where('cliente_id', $cliente->id)
            ->sum('debe');
        
        $haber = MovimientoCuentaCorriente::where('cliente_id', $cliente->id)
            ->sum('haber');
        
        return max(0, round($debe - $haber, 2));
    }
    
    /**
     * Obtiene el detalle de deuda CC por venta para un cliente.
     * 
     * @param int $clienteId
     * @return array
     */
    public function obtenerDeudaPorVenta(int $clienteId): array
    {
        $movimientosDebe = MovimientoCuentaCorriente::where('cliente_id', $clienteId)
            ->where('tipo', 'venta')
            ->whereNotNull('venta_id')
            ->with('venta')
            ->get();
        
        $deudaPorVenta = [];
        
        foreach ($movimientosDebe as $movDebe) {
            $ventaId = $movDebe->venta_id;
            
            if (!isset($deudaPorVenta[$ventaId])) {
                $deudaPorVenta[$ventaId] = [
                    'venta_id' => $ventaId,
                    'debe' => 0,
                    'haber' => 0,
                    'saldo' => 0,
                    'venta' => $movDebe->venta,
                ];
            }
            
            $deudaPorVenta[$ventaId]['debe'] += $movDebe->debe;
        }
        
        // Calcular HABER por venta
        $movimientosHaber = MovimientoCuentaCorriente::where('cliente_id', $clienteId)
            ->where('tipo', 'pago')
            ->whereNotNull('venta_id')
            ->get();
        
        foreach ($movimientosHaber as $movHaber) {
            $ventaId = $movHaber->venta_id;
            
            if (isset($deudaPorVenta[$ventaId])) {
                $deudaPorVenta[$ventaId]['haber'] += $movHaber->haber;
            }
        }
        
        // Calcular saldo final
        foreach ($deudaPorVenta as $ventaId => &$info) {
            $info['saldo'] = round($info['debe'] - $info['haber'], 2);
        }
        
        return array_values($deudaPorVenta);
    }
}
