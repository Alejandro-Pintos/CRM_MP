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
                $pago->observaciones_cheque = $data['observaciones_cheque'] ?? null;
                
                \Log::info('PagoService - Cheque configurado', [
                    'estado_cheque' => $pago->estado_cheque,
                    'numero_cheque' => $pago->numero_cheque,
                    'fecha_cheque' => $pago->fecha_cheque
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

            // CRÍTICO: Solo reducir saldo del cliente si NO es cheque pendiente
            // Los cheques pendientes no cuentan como dinero cobrado
            $debeReducirSaldo = !$esCheque || ($esCheque && isset($data['estado_cheque']) && $data['estado_cheque'] === 'cobrado');
            
            if ($debeReducirSaldo) {
                // Disminuir saldo del cliente (la lógica original de cheques)
                $cliente->saldo_actual = round((float)$cliente->saldo_actual - $monto, 2);
                $cliente->save();

                // Crear movimiento de cuenta corriente (pago = monto negativo, reduce deuda)
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
                }
            }

            // Cargar la relación metodoPago antes de devolver
            $pago->load('metodoPago');

            return $pago;
        });
    }
}
