<?php
namespace App\Services;

use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Pago;
use App\Models\MovimientoCuentaCorriente;
use App\Models\ComprobanteNumeracion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaService
{
    /**
     * Crea una venta con sus ítems, calcula el total, valida límite de crédito y procesa pagos.
     * @param array $payload Datos validados del request (cliente_id, fecha?, items[], pagos[]?, tipo_comprobante?, numero_comprobante?).
     * @param int   $usuarioId ID del usuario autenticado (quien registra la venta).
     * @return \App\Models\Venta Venta creada con relación 'items' y 'pagos' cargada.
     * @throws \Illuminate\Validation\ValidationException Si supera límite de crédito.
     */
    public function crearVenta(array $payload, int $usuarioId): Venta
    {
        return DB::transaction(function () use ($payload, $usuarioId) {

            // 1) Bloqueamos el cliente para evitar condiciones de carrera en saldo/limite
            $cliente = Cliente::lockForUpdate()->findOrFail($payload['cliente_id']);

            // 2) Calcular total desde items (cantidad * precio * (1 + iva/100))
            $total = 0.0;
            foreach ($payload['items'] as $it) {
                $cant = (float) $it['cantidad'];
                $precio = (float) $it['precio_unitario'];
                $iva = isset($it['iva']) ? (float) $it['iva'] : 0.0;
                $subtotal = $cant * $precio * (1 + $iva / 100);
                $total += $subtotal;
            }
            $total = round($total, 2);

            // 3) Calcular total de pagos
            $totalPagos = 0.0;
            if (isset($payload['pagos']) && is_array($payload['pagos'])) {
                foreach ($payload['pagos'] as $pago) {
                    $totalPagos += (float)$pago['monto'];
                }
            }
            $totalPagos = round($totalPagos, 2);

            // 4) Calcular saldo pendiente (lo que queda a deber)
            $saldoPendiente = round($total - $totalPagos, 2);

            // 5) Si el cliente tiene cuenta corriente, validar límite de crédito
            $tieneCuentaCorriente = (float)$cliente->limite_credito > 0;
            
            if ($saldoPendiente > 0) {
                if ($tieneCuentaCorriente) {
                    // Validar límite de crédito solo si queda saldo pendiente
                    $credito_disponible = (float)$cliente->limite_credito - (float)$cliente->saldo_actual;
                    if ($saldoPendiente > $credito_disponible) {
                        throw ValidationException::withMessages([
                            'limite_credito' => 'El saldo pendiente de la venta supera el límite de crédito disponible.'
                        ]);
                    }
                } else {
                    // Cliente sin cuenta corriente no puede tener saldo pendiente
                    throw ValidationException::withMessages([
                        'pago' => 'El cliente no tiene cuenta corriente. Debe pagar el total de la venta.'
                    ]);
                }
            }

            // 6) Generar número de comprobante automáticamente si se especificó tipo
            $numeroComprobante = null;
            if (!empty($payload['tipo_comprobante'])) {
                $numeroComprobante = ComprobanteNumeracion::generarNumero($payload['tipo_comprobante']);
            }

            // 7) Crear cabecera de venta
            $venta = new Venta();
            $venta->cliente_id = $cliente->id;
            $venta->usuario_id = $usuarioId;
            $venta->fecha = $payload['fecha'] ?? now();
            $venta->tipo_comprobante = $payload['tipo_comprobante'] ?? null;
            $venta->numero_comprobante = $numeroComprobante;
            $venta->total = $total;
            
            // Determinar estado de pago
            if ($totalPagos >= $total) {
                $venta->estado_pago = 'pagado';
            } elseif ($totalPagos > 0) {
                $venta->estado_pago = 'parcial';
            } else {
                $venta->estado_pago = 'pendiente';
            }
            
            $venta->save();

            // 6.1) Si viene de un pedido, asociarlo y actualizar estado
            if (!empty($payload['pedido_id'])) {
                $pedido = Pedido::find($payload['pedido_id']);
                if ($pedido) {
                    $pedido->venta_id = $venta->id;
                    $pedido->estado = 'entregado';
                    $pedido->save();
                }
            }

            // 7) Crear ítems (detalle_venta)
            foreach ($payload['items'] as $it) {
                DetalleVenta::create([
                    'venta_id'        => $venta->id,
                    'producto_id'     => $it['producto_id'],
                    'cantidad'        => $it['cantidad'],
                    'precio_unitario' => $it['precio_unitario'],
                    'iva'             => $it['iva'] ?? 0,
                ]);
            }

            // 8) Procesar pagos
            if (isset($payload['pagos']) && is_array($payload['pagos'])) {
                foreach ($payload['pagos'] as $pagoData) {
                    Pago::create([
                        'venta_id' => $venta->id,
                        'metodo_pago_id' => $pagoData['metodo_pago_id'],
                        'monto' => $pagoData['monto'],
                        'fecha_pago' => $pagoData['fecha_pago'] ?? now(),
                    ]);
                }
            }

            // 9) Actualizar saldo del cliente SOLO con lo que queda pendiente
            if ($saldoPendiente > 0) {
                $cliente->saldo_actual = round((float)$cliente->saldo_actual + $saldoPendiente, 2);
                $cliente->save();

                // 9.1) Crear movimiento en cuenta corriente (DEBE = aumenta deuda)
                if ($tieneCuentaCorriente) {
                    MovimientoCuentaCorriente::create([
                        'cliente_id' => $cliente->id,
                        'tipo' => 'debe',
                        'descripcion' => "Venta #{$venta->id}" . ($venta->numero_comprobante ? " - {$venta->tipo_comprobante} {$venta->numero_comprobante}" : ''),
                        'debe' => $saldoPendiente,
                        'haber' => 0,
                        'saldo' => $cliente->saldo_actual,
                        'fecha' => $venta->fecha,
                    ]);
                }
            }

            // 10) Si hubo pagos, registrar movimientos (HABER = reduce deuda)
            if ($totalPagos > 0 && $tieneCuentaCorriente) {
                MovimientoCuentaCorriente::create([
                    'cliente_id' => $cliente->id,
                    'tipo' => 'haber',
                    'descripcion' => "Pago venta #{$venta->id}",
                    'debe' => 0,
                    'haber' => $totalPagos,
                    'saldo' => $cliente->saldo_actual,
                    'fecha' => $venta->fecha,
                ]);
            }

            // 11) Devolver la venta con sus relaciones cargadas
            return $venta->load(['items', 'pagos']);
        });
    }
}
