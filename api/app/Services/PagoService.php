<?php
namespace App\Services;

use App\Models\Pago;
use App\Models\Venta;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\MovimientoCuentaCorriente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PagoService
{
    /**
     * Registra un pago para una venta, actualiza estado de pago, saldo del cliente
     * y crea movimiento de cuenta corriente. Devuelve el Pago creado.
     */
    public function registrarPago(Venta $venta, array $data): Pago
    {
        return DB::transaction(function () use ($venta, $data) {

            // Bloquear cliente para consistencia de saldo
            $cliente = Cliente::lockForUpdate()->findOrFail($venta->cliente_id);

            $monto = round((float)$data['monto'], 2);
            
            // Obtener ID de Cuenta Corriente
            $cuentaCorrienteId = MetodoPago::where('nombre', 'Cuenta Corriente')->value('id');
            $esCuentaCorriente = $data['metodo_pago_id'] == $cuentaCorrienteId;

            // Calcular totales actuales
            $totalPagadoReal = (float) $venta->pagos()
                ->where('metodo_pago_id', '!=', $cuentaCorrienteId)
                ->where(function($query) {
                    $query->whereNull('estado_cheque')
                          ->orWhere('estado_cheque', 'cobrado');
                })
                ->sum('monto');
            
            $totalChequesPendientes = (float) $venta->pagos()
                ->where('metodo_pago_id', '!=', $cuentaCorrienteId)
                ->where('estado_cheque', 'pendiente')
                ->sum('monto');
            
            $totalCuentaCorriente = (float) $venta->pagos()
                ->where('metodo_pago_id', $cuentaCorrienteId)
                ->sum('monto');

            $saldoSinAsignar = round((float)$venta->total - $totalPagadoReal - $totalChequesPendientes - $totalCuentaCorriente, 2);
            
            // Validaciones
            if ($esCuentaCorriente) {
                // VALIDACIÓN CRÍTICA: Cuenta Corriente solo se usa para FIAR saldo pendiente
                // NO para pagar deuda ya existente en CC
                if ($saldoSinAsignar <= 0) {
                    throw ValidationException::withMessages([
                        'metodo_pago_id' => 'No hay saldo pendiente de la venta para asignar a cuenta corriente. Use el método "Cuenta Corriente" solo para fiar saldo sin asignar, no para pagar deuda existente.'
                    ]);
                }
                
                // Si es cuenta corriente, solo puede asignar el saldo sin asignar
                if ($monto > $saldoSinAsignar + 0.01) {
                    throw ValidationException::withMessages([
                        'monto' => sprintf(
                            'El monto ($%s) excede el saldo sin asignar ($%s)',
                            number_format($monto, 2),
                            number_format($saldoSinAsignar, 2)
                        )
                    ]);
                }
                
                // Validar límite de crédito disponible
                if ((float)$cliente->limite_credito > 0) {
                    $saldoActual = (float)$cliente->saldo_actual;
                    $nuevoSaldo = $saldoActual + $monto;
                    $limiteCredito = (float)$cliente->limite_credito;
                    
                    if ($nuevoSaldo > $limiteCredito) {
                        throw ValidationException::withMessages([
                            'monto' => sprintf(
                                'El monto ($%s) supera el crédito disponible. Saldo actual: $%s, Límite: $%s, Disponible: $%s',
                                number_format($monto, 2),
                                number_format($saldoActual, 2),
                                number_format($limiteCredito, 2),
                                number_format($limiteCredito - $saldoActual, 2)
                            )
                        ]);
                    }
                }
            } else {
                // Si es pago real, puede pagar: saldo sin asignar + deuda de C.C.
                $saldoDisponibleParaPagar = $saldoSinAsignar + $totalCuentaCorriente;
                
                if ($monto > $saldoDisponibleParaPagar + 0.01) {
                    throw ValidationException::withMessages([
                        'monto' => sprintf(
                            'El monto ($%s) excede el saldo disponible ($%s). Sin asignar: $%s, Deuda C.C.: $%s',
                            number_format($monto, 2),
                            number_format($saldoDisponibleParaPagar, 2),
                            number_format($saldoSinAsignar, 2),
                            number_format($totalCuentaCorriente, 2)
                        )
                    ]);
                }
                
                // Si hay deuda en C.C., primero cancelamos esa deuda
                if ($totalCuentaCorriente > 0) {
                    $montoCancelaDeuda = min($monto, $totalCuentaCorriente);
                    
                    // Eliminar o reducir los registros de cuenta corriente
                    $pagosCC = $venta->pagos()->where('metodo_pago_id', $cuentaCorrienteId)->orderBy('id')->get();
                    $restantePorCancelar = $montoCancelaDeuda;
                    
                    foreach ($pagosCC as $pagoCC) {
                        if ($restantePorCancelar <= 0) break;
                        
                        $montoCC = (float)$pagoCC->monto;
                        if ($montoCC <= $restantePorCancelar) {
                            // Eliminar completamente este registro de deuda
                            $restantePorCancelar -= $montoCC;
                            $pagoCC->delete();
                        } else {
                            // Reducir parcialmente este registro de deuda
                            $pagoCC->monto = $montoCC - $restantePorCancelar;
                            $pagoCC->save();
                            $restantePorCancelar = 0;
                        }
                    }
                }
            }

            // Crear pago
            $pago = new Pago();
            $pago->venta_id = $venta->id;
            $pago->metodo_pago_id = $data['metodo_pago_id'];
            $pago->monto = $monto;
            $pago->fecha_pago = $data['fecha_pago'] ?? now();
            
            // Verificar si el método de pago es cheque
            $metodoPago = MetodoPago::find($data['metodo_pago_id']);
            $esCheque = $metodoPago && strtolower($metodoPago->nombre) === 'cheque';
            
            \Log::info('PagoService - Creando pago', [
                'metodo_id' => $data['metodo_pago_id'],
                'metodo_nombre' => $metodoPago ? $metodoPago->nombre : 'NULL',
                'es_cheque' => $esCheque,
                'numero_cheque_recibido' => $data['numero_cheque'] ?? 'NULL',
                'fecha_cheque_recibida' => $data['fecha_cheque'] ?? 'NULL'
            ]);
            
            if ($esCheque) {
                // Si es cheque, marcar como pendiente por defecto
                $pago->estado_cheque = 'pendiente';
                $pago->numero_cheque = $data['numero_cheque'] ?? null;
                $pago->fecha_cheque = $data['fecha_cheque'] ?? null;
                $pago->fecha_cobro = $data['fecha_cobro'] ?? null;
                $pago->observaciones_cheque = $data['observaciones_cheque'] ?? null;
                
                \Log::info('PagoService - Cheque configurado', [
                    'estado_cheque' => $pago->estado_cheque,
                    'numero_cheque' => $pago->numero_cheque,
                    'fecha_cheque' => $pago->fecha_cheque,
                    'fecha_cobro' => $pago->fecha_cobro
                ]);
            }
            
            $pago->save();
            
            \Log::info('PagoService - Pago guardado', [
                'pago_id' => $pago->id,
                'estado_cheque_guardado' => $pago->estado_cheque
            ]);

            // CRÍTICO: Recargar pagos ANTES de guardar venta para que el accessor calcule correctamente
            $venta->load('pagos');
            
            // Forzar recálculo del estado_pago
            $estadoCalculado = $venta->estado_pago; // Esto ejecuta el accessor
            $venta->estado_pago = $estadoCalculado; // Lo asigna explícitamente
            $venta->save(); // Guarda el estado correcto

            // CRÍTICO: Distinguir entre pagos reales y asignación a Cuenta Corriente
            
            if ($esCuentaCorriente) {
                // CASO 1: Asignación a Cuenta Corriente (FIAR saldo pendiente)
                // Este pago posterior asigna parte del saldo sin asignar de la venta a cuenta corriente
                // DEBE crear movimiento tipo "venta" (DEBE) para incrementar deuda del cliente
                
                \Log::info("Validando asignación a Cuenta Corriente: Venta #{$venta->id}, Cliente #{$cliente->id}, Monto: {$monto}");
                
                // VALIDAR límite de crédito ANTES de asignar
                $saldoActual = $cliente->calcularSaldoReal();
                $nuevoSaldo = $saldoActual + $monto;
                
                if ($nuevoSaldo > (float)$cliente->limite_credito + 0.01) {
                    throw ValidationException::withMessages([
                        'monto' => sprintf(
                            'No se puede asignar $%s a cuenta corriente. ' .
                            'Excedería el límite de crédito ($%s). ' .
                            'Deuda actual: $%s, Disponible: $%s',
                            number_format($monto, 2, ',', '.'),
                            number_format($cliente->limite_credito, 2, ',', '.'),
                            number_format($saldoActual, 2, ',', '.'),
                            number_format(max(0, $cliente->limite_credito - $saldoActual), 2, ',', '.')
                        )
                    ]);
                }
                
                \Log::info("Asignación válida. Registrando en Cuenta Corriente...");
                
                // Incrementar saldo_actual del cliente (deuda)
                $cliente->saldo_actual = round((float)$cliente->saldo_actual + $monto, 2);
                $cliente->save();
                
                // Crear movimiento de cuenta corriente tipo "venta" (DEBE)
                if ((float)$cliente->limite_credito > 0) {
                    MovimientoCuentaCorriente::create([
                        'cliente_id'   => $cliente->id,
                        'tipo'         => 'venta',
                        'referencia_id'=> $venta->id,
                        'monto'        => abs($monto),
                        'debe'         => abs($monto), // DEBE = cliente debe dinero
                        'haber'        => 0,
                        'fecha'        => $pago->fecha_pago,
                        'descripcion'  => "Venta a crédito #{$venta->id} (pago posterior asignado a CC)",
                    ]);
                    
                    // Recalcular saldo del cliente basado en movimientos
                    $cliente->refresh();
                    $cliente->recalcularSaldo();
                }
                
            } else {
                // CASO 2: Pago Real (Efectivo, Transferencia, etc.) o Cheque
                // Solo reducir saldo si NO es cheque pendiente
                $debeReducirSaldo = !$esCheque || ($esCheque && isset($data['estado_cheque']) && $data['estado_cheque'] === 'cobrado');
                
                if ($debeReducirSaldo) {
                    // VALIDAR que no haya sobrepago
                    $saldoActual = $cliente->calcularSaldoReal();
                    
                    if ($monto > $saldoActual + 0.01) {
                        throw ValidationException::withMessages([
                            'monto' => sprintf(
                                'El monto del pago ($%s) excede la deuda actual del cliente ($%s). ' .
                                'Máximo permitido: $%s',
                                number_format($monto, 2, ',', '.'),
                                number_format($saldoActual, 2, ',', '.'),
                                number_format($saldoActual, 2, ',', '.')
                            )
                        ]);
                    }
                    
                    // Disminuir saldo del cliente (dinero que ingresa)
                    $cliente->saldo_actual = round((float)$cliente->saldo_actual - $monto, 2);
                    $cliente->save();

                    // Crear movimiento de cuenta corriente (pago = HABER, reduce deuda)
                    if ((float)$cliente->limite_credito > 0) {
                        MovimientoCuentaCorriente::create([
                            'cliente_id'   => $cliente->id,
                            'tipo'         => 'pago',
                            'referencia_id'=> $pago->id,
                            'monto'        => -abs($monto), // Negativo = reduce deuda
                            'debe'         => 0,
                            'haber'        => abs($monto),  // Pago = HABER (reduce deuda)
                            'fecha'        => $pago->fecha_pago,
                            'descripcion'  => 'Pago venta #'.$venta->id . ($esCheque ? ' (Cheque cobrado)' : ''),
                        ]);
                        
                        // Recalcular saldo del cliente basado en movimientos
                        $cliente->refresh();
                        $cliente->recalcularSaldo();
                    }
                    
                    \Log::info("Pago registrado: Venta #{$venta->id}, Cliente #{$cliente->id}, Monto: {$monto}");
                }
            }

            // Cargar la relación metodoPago antes de devolver
            $pago->load('metodoPago');

            return $pago;
        });
    }
}
