<?php
namespace App\Services\Ventas;

use App\Models\Venta;
use App\Models\MetodoPago;
use App\Models\Cheque;

/**
 * Servicio de dominio para calcular el resumen de pagos de una venta
 * 
 * REGLAS DE NEGOCIO CRÍTICAS:
 * 
 * 1. Total Cobrado: suma de pagos reales (efectivo, transferencia, etc.) + cheques COBRADOS
 * 2. Cheques Pendientes: suma de cheques con estado 'pendiente' (NO se cuentan como cobrados)
 * 3. Cheques Cobrados: suma de cheques con estado 'cobrado' (SÍ se cuentan como cobrados)
 * 4. Total Deuda CC: suma de pagos con método "Cuenta Corriente"
 * 5. Saldo Pendiente: Total Venta - (Total Cobrado + Cheques Cobrados + Total Deuda CC)
 * 
 * FUENTE DE VERDAD:
 * - Estado de cheques: tabla `cheques` campo `estado`
 * - NO usar `pagos.estado_cheque` (campo legacy)
 */
class ResumenPagosVentaService
{
    /**
     * Calcular resumen de pagos de una venta
     * 
     * @param Venta $venta
     * @return array{
     *   total_venta: float,
     *   total_cobrado: float,
     *   cheques_pendientes: float,
     *   cheques_cobrados: float,
     *   cheques_rechazados: float,
     *   total_deuda_cc: float,
     *   saldo_pendiente: float
     * }
     */
    public function calcular(Venta $venta): array
    {
        // Cargar relaciones necesarias
        $venta->loadMissing(['pagos.metodoPago', 'cheques']);
        
        $totalVenta = (float) $venta->total;
        
        // Obtener IDs de métodos especiales
        $metodoChequeId = MetodoPago::where('nombre', 'Cheque')->value('id');
        $metodoCuentaCorrienteId = MetodoPago::where('nombre', 'Cuenta Corriente')->value('id');
        
        $totalCobrado = 0.0;
        $totalDeudaCC = 0.0;
        
        // PASO 1: Calcular pagos reales (efectivo, transferencia, etc.)
        // EXCLUYE: Cheques (se calculan por separado) y Cuenta Corriente (es deuda)
        foreach ($venta->pagos as $pago) {
            $monto = (float) $pago->monto;
            
            if ($pago->metodo_pago_id == $metodoChequeId) {
                // Los cheques se manejan por separado desde la tabla `cheques`
                continue;
            } elseif ($pago->metodo_pago_id == $metodoCuentaCorrienteId) {
                // Cuenta corriente es deuda, no cobro
                $totalDeudaCC += $monto;
            } else {
                // Cualquier otro método (efectivo, transferencia, etc.)
                $totalCobrado += $monto;
            }
        }
        
        // PASO 2: Calcular cheques por estado desde la tabla `cheques` (FUENTE DE VERDAD)
        $chequesPendientes = 0.0;
        $chequesCobrados = 0.0;
        $chequesRechazados = 0.0;
        
        foreach ($venta->cheques as $cheque) {
            $monto = (float) $cheque->monto;
            
            switch ($cheque->estado) {
                case Cheque::ESTADO_PENDIENTE:
                    $chequesPendientes += $monto;
                    break;
                case Cheque::ESTADO_COBRADO:
                    $chequesCobrados += $monto;
                    break;
                case Cheque::ESTADO_RECHAZADO:
                    $chequesRechazados += $monto;
                    break;
            }
        }
        
        // PASO 3: Total cobrado incluye pagos reales + cheques cobrados
        $totalCobradoConCheques = $totalCobrado + $chequesCobrados;
        
        // PASO 4: Saldo pendiente = lo que falta cobrar
        // NO incluye cheques pendientes (aún no ingresó el dinero)
        $saldoPendiente = $totalVenta - ($totalCobradoConCheques + $totalDeudaCC);
        
        // Evitar negativos por errores de redondeo
        if (abs($saldoPendiente) < 0.01) {
            $saldoPendiente = 0.0;
        }
        
        return [
            'total_venta' => round($totalVenta, 2),
            'total_cobrado' => round($totalCobradoConCheques, 2), // Incluye cheques cobrados
            'cheques_pendientes' => round($chequesPendientes, 2),
            'cheques_cobrados' => round($chequesCobrados, 2),
            'cheques_rechazados' => round($chequesRechazados, 2),
            'total_deuda_cc' => round($totalDeudaCC, 2),
            'saldo_pendiente' => round($saldoPendiente, 2),
        ];
    }
}
