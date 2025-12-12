<?php
namespace App\Services\Ventas;

use App\Models\Venta;
use App\Models\MetodoPago;
use App\Models\Cheque;
use App\Models\MovimientoCuentaCorriente;
use App\Services\Finanzas\CuentaCorrienteService;

/**
 * Servicio de dominio para calcular el resumen de pagos de una venta
 * 
 * REGLAS DE NEGOCIO CRÍTICAS:
 * 
 * 1. Total Cobrado: suma de pagos reales (efectivo, transferencia, etc.) + cheques COBRADOS
 * 2. Cheques Pendientes: suma de cheques con estado 'pendiente' (NO se cuentan como cobrados)
 * 3. Cheques Cobrados: suma de cheques con estado 'cobrado' (SÍ se cuentan como cobrados)
 * 4. Total Deuda CC: deuda REAL calculada desde movimientos de cuenta corriente (considera pagos posteriores)
 * 5. Saldo Pendiente: Total Venta - (Total Cobrado + Cheques Cobrados + Deuda CC Real)
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
     *   total_deuda_cc_original: float,
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
        
        // PASO 1.5: Calcular monto ORIGINAL de CC desde movimientos (DEBE tipo venta)
        // Esto es necesario porque cuando se crea la venta, NO se crea un pago con método CC
        // sino que se registra directamente en movimientos_cuenta_corriente
        $totalDeudaCCDesdeMovimientos = (float) MovimientoCuentaCorriente::where('venta_id', $venta->id)
            ->where('tipo', 'venta')
            ->sum('debe');
        
        // Usar el mayor valor (por si hay pagos CC Y movimientos CC)
        $totalDeudaCC = max($totalDeudaCC, $totalDeudaCCDesdeMovimientos);
        
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
        
        // PASO 4: Calcular deuda CC REAL desde movimientos de cuenta corriente
        // Esto considera pagos posteriores que hayan reducido la deuda
        $deudaCCReal = 0.0;
        try {
            $ccService = new CuentaCorrienteService();
            $deudaCCReal = $ccService->calcularDeudaCCVenta($venta->id);
        } catch (\Throwable $e) {
            \Log::error("Error calculando deuda CC real de venta #{$venta->id}: " . $e->getMessage());
            // Fallback: usar el total de pagos CC como aproximación
            $deudaCCReal = $totalDeudaCC;
        }
        
        // PASO 5: Saldo pendiente = lo que falta cobrar
        // FÓRMULA: Total - Cobrado - Deuda CC ORIGINAL
        // IMPORTANTE: Usamos totalDeudaCC (original) no deudaCCReal (actual)
        // porque el saldo pendiente no debe contar pagos posteriores a la CC
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
            'total_deuda_cc' => round($deudaCCReal, 2), // Deuda REAL desde movimientos de CC (deuda pendiente actual)
            'total_deuda_cc_original' => round($totalDeudaCC, 2), // Monto original asignado a CC en la venta
            'saldo_pendiente' => round($saldoPendiente, 2),
        ];
    }
}
