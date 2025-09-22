<?php
namespace App\Services;

use App\Models\Pago;
use App\Models\Venta;
use App\Models\Cliente;
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

            // Calcular saldo pendiente
            $totalPagado = (float) $venta->pagos()->sum('monto');
            $saldoPendiente = round((float)$venta->total - $totalPagado, 2);

            $monto = round((float)$data['monto'], 2);
            if ($monto > $saldoPendiente + 0.0001) {
                throw ValidationException::withMessages([
                    'monto' => 'El monto excede el saldo pendiente de la venta (overpayment).'
                ]);
            }

            // Crear pago
            $pago = new Pago();
            $pago->venta_id = $venta->id;
            $pago->metodo_pago_id = $data['metodo_pago_id'];
            $pago->monto = $monto;
            $pago->fecha_pago = $data['fecha_pago'] ?? now();
            $pago->save();

            // Actualizar estado de la venta
            $totalPagadoNuevo = $totalPagado + $monto;
            if ($totalPagadoNuevo >= (float)$venta->total - 0.0001) {
                $venta->estado_pago = 'pagado';
            } elseif ($totalPagadoNuevo > 0) {
                $venta->estado_pago = 'parcial';
            } else {
                $venta->estado_pago = 'pendiente';
            }
            $venta->save();

            // Disminuir saldo del cliente
            $cliente->saldo_actual = round((float)$cliente->saldo_actual - $monto, 2);
            $cliente->save();

            // Movimiento de cuenta corriente (pago = monto negativo)
            MovimientoCuentaCorriente::create([
                'cliente_id'   => $cliente->id,
                'tipo'         => 'pago',
                'referencia_id'=> $pago->id,
                'monto'        => -$monto,
                'fecha'        => $pago->fecha_pago,
                'descripcion'  => 'Pago venta #'.$venta->id,
            ]);

            return $pago;
        });
    }
}
