<?php
namespace App\Services;

use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Pago;
use App\Models\MetodoPago;
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

            // 3) Calcular total de pagos (EXCLUYENDO Cuenta Corriente)
            $totalPagos = 0.0;
            if (isset($payload['pagos']) && is_array($payload['pagos'])) {
                foreach ($payload['pagos'] as $pago) {
                    // Obtener el método de pago para verificar si es Cuenta Corriente
                    $metodoPago = MetodoPago::find($pago['metodo_pago_id']);
                    $esCuentaCorriente = $metodoPago && strtolower($metodoPago->nombre) === 'cuenta corriente';
                    
                    // Solo sumar si NO es Cuenta Corriente
                    if (!$esCuentaCorriente) {
                        $totalPagos += (float)$pago['monto'];
                    }
                }
            }
            $totalPagos = round($totalPagos, 2);

            // 4) Calcular saldo pendiente (lo que queda a deber)
            $saldoPendiente = round($total - $totalPagos, 2);

            // DEBUG temporal
            \Log::info('Total calculado:', ['total' => $total]);
            \Log::info('Total pagos:', ['totalPagos' => $totalPagos]);
            \Log::info('Saldo pendiente:', ['saldoPendiente' => $saldoPendiente]);
            \Log::info('Cliente:', ['id' => $cliente->id, 'limite_credito' => $cliente->limite_credito]);

            // 5) Si el cliente tiene cuenta corriente, validar límite de crédito
            $tieneCuentaCorriente = (float)$cliente->limite_credito > 0;
            
            // Tolerancia de 0.01 para evitar errores de redondeo (1 centavo)
            $tolerancia = 0.01;
            
            if ($saldoPendiente > $tolerancia) {
                if ($tieneCuentaCorriente) {
                    // Validar límite de crédito solo si queda saldo pendiente
                    // Calcular saldo REAL en tiempo real (no confiar en BD)
                    $saldoRealActual = $cliente->calcularSaldoReal();
                    
                    // Calcular crédito disponible actual
                    $credito_disponible = (float)$cliente->limite_credito - $saldoRealActual;
                    
                    // Calcular saldo proyectado después de esta venta
                    $saldoProyectado = $saldoRealActual + $saldoPendiente;
                    
                    // VALIDAR que el saldo proyectado NO exceda el límite
                    if ($saldoProyectado > (float)$cliente->limite_credito + $tolerancia) {
                        throw ValidationException::withMessages([
                            'limite_credito' => sprintf(
                                'La operación excedería el límite de crédito. ' .
                                'Límite: $%s, Deuda actual: $%s, Saldo pendiente: $%s, ' .
                                'Total proyectado: $%s (exceso: $%s)',
                                number_format($cliente->limite_credito, 2, ',', '.'),
                                number_format($saldoRealActual, 2, ',', '.'),
                                number_format($saldoPendiente, 2, ',', '.'),
                                number_format($saldoProyectado, 2, ',', '.'),
                                number_format(max(0, $saldoProyectado - $cliente->limite_credito), 2, ',', '.')
                            )
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
            
            // Determinar estado de pago (con tolerancia de 1 centavo)
            if ($saldoPendiente <= $tolerancia) {
                $venta->estado_pago = 'pagado';
            } elseif ($totalPagos > $tolerancia) {
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

            // 8) Procesar pagos (EXCLUYENDO Cuenta Corriente)
            // Los pagos con método "Cuenta Corriente" no se registran aquí,
            // se manejan en el paso 9 como movimiento de cuenta corriente
            if (isset($payload['pagos']) && is_array($payload['pagos'])) {
                foreach ($payload['pagos'] as $pagoData) {
                    // Verificar el tipo de método de pago
                    $metodoPago = MetodoPago::find($pagoData['metodo_pago_id']);
                    $esCheque = $metodoPago && strtolower($metodoPago->nombre) === 'cheque';
                    $esCuentaCorriente = $metodoPago && strtolower($metodoPago->nombre) === 'cuenta corriente';
                    
                    // Si es Cuenta Corriente, NO crear el pago aquí (se maneja en paso 9)
                    if ($esCuentaCorriente) {
                        continue;
                    }
                    
                    $nuevoPago = [
                        'venta_id' => $venta->id,
                        'metodo_pago_id' => $pagoData['metodo_pago_id'],
                        'monto' => $pagoData['monto'],
                        'fecha_pago' => $pagoData['fecha_pago'] ?? now(),
                    ];
                    
                    // Si es cheque, agregar campos específicos
                    if ($esCheque) {
                        $nuevoPago['estado_cheque'] = 'pendiente';
                        $nuevoPago['numero_cheque'] = $pagoData['numero_cheque'] ?? null;
                        $nuevoPago['fecha_cheque'] = $pagoData['fecha_cheque'] ?? null;
                        $nuevoPago['observaciones_cheque'] = $pagoData['observaciones_cheque'] ?? null;
                    }
                    
                    Pago::create($nuevoPago);
                }
            }

            // 9) LÓGICA CORREGIDA: Registrar venta a crédito SOLO si hay saldo pendiente
            // El saldo pendiente se registra como movimiento tipo "venta" (DEBE) en cuenta corriente
            // NO se crea un "pago" porque el cliente AÚN NO HA PAGADO, está fiado
            \Log::info('PASO 9 - Verificar saldo pendiente', [
                'saldoPendiente' => $saldoPendiente,
                'tolerancia' => $tolerancia,
                'tieneCuentaCorriente' => $tieneCuentaCorriente
            ]);
            
            if ($saldoPendiente > $tolerancia && $tieneCuentaCorriente) {
                \Log::info('PASO 9.1 - Registrando venta a crédito en cuenta corriente', [
                    'saldo_anterior' => $cliente->saldo_actual,
                    'monto_fiado' => $saldoPendiente,
                    'saldo_nuevo' => (float)$cliente->saldo_actual + $saldoPendiente
                ]);
                
                $cliente->saldo_actual = round((float)$cliente->saldo_actual + $saldoPendiente, 2);
                $cliente->save();

                // CORRECCIÓN CRÍTICA: Crear movimiento tipo "venta" (DEBE), NO un "pago"
                // Esto registra que el cliente DEBE dinero por esta venta
                MovimientoCuentaCorriente::create([
                    'cliente_id' => $cliente->id,
                    'tipo' => 'venta',
                    'referencia_id' => $venta->id,
                    'monto' => abs($saldoPendiente), // Monto positivo representa deuda
                    'debe' => abs($saldoPendiente),  // DEBE = cliente debe dinero
                    'haber' => 0,
                    'fecha' => $venta->fecha,
                    'descripcion' => "Venta a crédito #{$venta->id}" . ($venta->numero_comprobante ? " - {$venta->tipo_comprobante} {$venta->numero_comprobante}" : ''),
                ]);
                
                \Log::info('PASO 9.2 - Movimiento de venta (DEBE) creado exitosamente');
                
                // Recalcular saldo del cliente basado en movimientos
                $cliente->refresh();
                $cliente->recalcularSaldo();
            }

            // 11) Devolver la venta con sus relaciones cargadas
            return $venta->load(['items', 'pagos']);
        });
    }
}
