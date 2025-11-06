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
            
            if ($esCheque) {
                // Si es cheque, marcar como pendiente por defecto
                $pago->estado_cheque = 'pendiente';
                $pago->numero_cheque = $data['numero_cheque'] ?? null;
                $pago->fecha_cheque = $data['fecha_cheque'] ?? null;
                $pago->observaciones_cheque = $data['observaciones_cheque'] ?? null;
            }
            
            $pago->save();

            // El estado se calculará automáticamente mediante el accessor
            // Solo necesitamos recargar la relación pagos para que el accessor funcione
            $venta->load('pagos');
            $venta->save(); // Esto guardará el estado calculado por el accessor

            // Disminuir saldo del cliente
            $cliente->saldo_actual = round((float)$cliente->saldo_actual - $monto, 2);
            $cliente->save();

            // Crear movimiento de cuenta corriente (pago = monto negativo, reduce deuda)
            if ((float)$cliente->limite_credito > 0) {
                MovimientoCuentaCorriente::create([
                    'cliente_id'   => $cliente->id,
                    'tipo'         => 'pago',
                    'referencia_id'=> $pago->id,
                    'monto'        => -abs($monto), // Negativo = reduce deuda
                    'fecha'        => $pago->fecha_pago,
                    'descripcion'  => 'Pago venta #'.$venta->id,
                ]);
            }

            // Cargar la relación metodoPago antes de devolver
            $pago->load('metodoPago');

            return $pago;
        });
    }
}
