<?php
namespace App\Services;

use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaService
{
    /**
     * Crea una venta con sus ítems, calcula el total y valida límite de crédito.
     * @param array $payload Datos validados del request (cliente_id, fecha?, items[]…).
     * @param int   $usuarioId ID del usuario autenticado (quien registra la venta).
     * @return \App\Models\Venta Venta creada con relación 'items' cargada.
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

            // 3) Validar límite de crédito (venta a crédito, sin pago inicial por ahora)
            $credito_disponible = (float)$cliente->limite_credito - (float)$cliente->saldo_actual;
            if ($total > $credito_disponible) {
                throw ValidationException::withMessages([
                    'limite_credito' => 'El total de la venta supera el límite de crédito disponible.'
                ]);
            }

            // 4) Crear cabecera de venta
            $venta = new Venta();
            $venta->cliente_id = $cliente->id;
            $venta->usuario_id = $usuarioId;
            $venta->fecha = $payload['fecha'] ?? now();
            $venta->estado_pago = 'pendiente'; // sin pagos por ahora
            $venta->tipo_comprobante = $payload['tipo_comprobante'] ?? null;
            $venta->numero_comprobante = $payload['numero_comprobante'] ?? null;
            $venta->total = $total;
            $venta->save();

            // 5) Crear ítems (detalle_venta)
            foreach ($payload['items'] as $it) {
                DetalleVenta::create([
                    'venta_id'        => $venta->id,
                    'producto_id'     => $it['producto_id'],
                    'cantidad'        => $it['cantidad'],
                    'precio_unitario' => $it['precio_unitario'],
                    'iva'             => $it['iva'] ?? 0,
                ]);
            }

            // 6) Aumentar saldo del cliente (porque queda adeudado)
            $cliente->saldo_actual = round((float)$cliente->saldo_actual + $total, 2);
            $cliente->save();

            // 7) Devolver la venta con sus items cargados
            return $venta->load('items');
        });
    }
}
