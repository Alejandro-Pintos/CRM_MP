<?php

namespace App\Services\Finanzas;

use App\Models\Cheque;
use App\Models\Venta;
use App\Models\Pago;
use App\Models\Cliente;
use App\Services\Finanzas\CuentaCorrienteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de dominio para gestión de cheques.
 * 
 * INVARIANTES GARANTIZADOS:
 * - Un cheque siempre está vinculado a un cliente y una venta.
 * - Cambios de estado de cheque impactan automáticamente en cuenta corriente.
 * - No se puede cobrar/rechazar un cheque ya procesado.
 * - Los cambios de estado generan trazabilidad completa.
 */
class ChequeService
{
    protected $cuentaCorrienteService;

    public function __construct(CuentaCorrienteService $cuentaCorrienteService)
    {
        $this->cuentaCorrienteService = $cuentaCorrienteService;
    }

    /**
     * Registra un cheque vinculado a una venta.
     * 
     * @param Venta $venta
     * @param array $data [monto, numero_cheque?, fecha_cheque?, fecha_vencimiento?, observaciones?]
     * @return Cheque
     */
    public function registrarChequeDesdeVenta(Venta $venta, array $data): Cheque
    {
        return DB::transaction(function () use ($venta, $data) {
            
            // INVARIANTE: El cheque siempre tiene cliente_id y venta_id
            $cheque = Cheque::create([
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'pago_id' => $data['pago_id'] ?? null, // Vincular con el registro de pago
                'numero' => $data['numero_cheque'] ?? null,
                'monto' => round((float)$data['monto'], 2),
                'fecha_emision' => $data['fecha_cheque'] ?? now(),
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'estado' => 'pendiente',
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            \Log::info('Cheque registrado desde venta', [
                'cheque_id' => $cheque->id,
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'monto' => $cheque->monto,
                'numero' => $cheque->numero,
            ]);

            return $cheque;
        });
    }

    /**
     * Marca un cheque como cobrado.
     * 
     * INVARIANTE: Solo cheques pendientes pueden cobrarse.
     * INVARIANTE: El cobro reduce la deuda del cliente en cuenta corriente.
     * 
     * @param Cheque $cheque
     * @param string|null $fechaCobro
     * @return Cheque
     * @throws ValidationException
     */
    public function marcarComoCobrado(Cheque $cheque, ?string $fechaCobro = null): Cheque
    {
        return DB::transaction(function () use ($cheque, $fechaCobro) {
            
            // Bloquear cheque para evitar condiciones de carrera
            $cheque = Cheque::lockForUpdate()->findOrFail($cheque->id);

            // VALIDACIÓN: Solo cheques pendientes pueden cobrarse
            if ($cheque->estado !== 'pendiente') {
                throw ValidationException::withMessages([
                    'estado' => 'El cheque ya fue procesado y no puede cobrarse nuevamente.'
                ]);
            }

            // Actualizar estado
            $cheque->estado = 'cobrado';
            $cheque->fecha_cobro = $fechaCobro ?? now();
            $cheque->save();

            // IMPACTO EN CUENTA CORRIENTE: Registrar pago real del cheque
            if ($cheque->venta_id) {
                $this->cuentaCorrienteService->registrarPagoPorCheque(
                    clienteId: $cheque->cliente_id,
                    ventaId: $cheque->venta_id,
                    monto: $cheque->monto,
                    fecha: $cheque->fecha_cobro,
                    observaciones: "Cheque #{$cheque->numero} cobrado"
                );
            }

            \Log::info('Cheque marcado como cobrado', [
                'cheque_id' => $cheque->id,
                'cliente_id' => $cheque->cliente_id,
                'venta_id' => $cheque->venta_id,
                'monto' => $cheque->monto,
                'fecha_cobro' => $cheque->fecha_cobro,
            ]);

            return $cheque;
        });
    }

    /**
     * Marca un cheque como rechazado.
     * 
     * INVARIANTE: Solo cheques pendientes pueden rechazarse.
     * INVARIANTE: El rechazo NO reduce la deuda (el cliente sigue debiendo).
     * 
     * @param Cheque $cheque
     * @param string|null $motivoRechazo
     * @return Cheque
     * @throws ValidationException
     */
    public function marcarComoRechazado(Cheque $cheque, ?string $motivoRechazo = null): Cheque
    {
        return DB::transaction(function () use ($cheque, $motivoRechazo) {
            
            // Bloquear cheque
            $cheque = Cheque::lockForUpdate()->findOrFail($cheque->id);

            // VALIDACIÓN: Solo cheques pendientes pueden rechazarse
            if ($cheque->estado !== 'pendiente') {
                throw ValidationException::withMessages([
                    'estado' => 'El cheque ya fue procesado.'
                ]);
            }

            // Actualizar estado
            $cheque->estado = 'rechazado';
            $cheque->fecha_rechazo = now();
            $cheque->motivo_rechazo = $motivoRechazo;
            $cheque->save();

            // IMPACTO EN CUENTA CORRIENTE: El cliente sigue debiendo el monto
            // No hacemos nada aquí porque la deuda ya existe desde que se creó la venta

            \Log::warning('Cheque rechazado', [
                'cheque_id' => $cheque->id,
                'cliente_id' => $cheque->cliente_id,
                'venta_id' => $cheque->venta_id,
                'monto' => $cheque->monto,
                'motivo' => $motivoRechazo,
            ]);

            return $cheque;
        });
    }

    /**
     * Actualiza datos administrativos de un cheque pendiente.
     * 
     * @param Cheque $cheque
     * @param array $data
     * @return Cheque
     */
    public function actualizarDatos(Cheque $cheque, array $data): Cheque
    {
        // Solo permitir actualizar si está pendiente
        if ($cheque->estado !== 'pendiente') {
            throw ValidationException::withMessages([
                'estado' => 'Solo se pueden editar cheques pendientes.'
            ]);
        }

        $cheque->update([
            'numero' => $data['numero'] ?? $cheque->numero,
            'fecha_emision' => $data['fecha_emision'] ?? $cheque->fecha_emision,
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? $cheque->fecha_vencimiento,
            'observaciones' => $data['observaciones'] ?? $cheque->observaciones,
        ]);

        return $cheque;
    }

    /**
     * Obtiene cheques pendientes con alertas de vencimiento.
     * 
     * @param int $diasAlerta Días antes del vencimiento para marcar como alerta
     * @return \Illuminate\Support\Collection
     */
    public function obtenerChequesPendientesConAlertas(int $diasAlerta = 7)
    {
        $hoy = now()->startOfDay();

        return Cheque::with(['cliente', 'venta'])
            ->where('estado', 'pendiente')
            ->get()
            ->map(function ($cheque) use ($hoy, $diasAlerta) {
                $cheque->vencido = false;
                $cheque->proximo_a_vencer = false;
                $cheque->dias_restantes = null;

                if ($cheque->fecha_vencimiento) {
                    $fechaVenc = \Carbon\Carbon::parse($cheque->fecha_vencimiento)->startOfDay();
                    $dias = $hoy->diffInDays($fechaVenc, false); // false = puede ser negativo

                    $cheque->dias_restantes = $dias;
                    $cheque->vencido = $dias < 0;
                    $cheque->proximo_a_vencer = $dias >= 0 && $dias <= $diasAlerta;
                }

                return $cheque;
            });
    }
}
