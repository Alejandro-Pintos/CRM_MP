<?php

namespace App\Services\Ventas;

use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\MetodoPago;
use App\Services\Finanzas\CuentaCorrienteService;
use App\Services\Finanzas\ChequeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio para registrar ventas con validación centralizada
 * 
 * INVARIANTES GARANTIZADOS:
 * - El total se calcula SIEMPRE desde items (nunca confiar en frontend)
 * - Si el cliente no tiene CC, debe pagar el 100% al contado
 * - Si tiene CC, se valida límite de crédito ANTES de crear
 * - Los pagos con cheque crean automáticamente registro en tabla cheques
 * - El estado_pago se calcula automáticamente
 */
class RegistrarVentaService
{
    protected $cuentaCorrienteService;
    protected $chequeService;

    public function __construct(
        CuentaCorrienteService $cuentaCorrienteService,
        ChequeService $chequeService
    ) {
        $this->cuentaCorrienteService = $cuentaCorrienteService;
        $this->chequeService = $chequeService;
    }

    /**
     * Ejecuta el registro de una nueva venta
     * 
     * @param Cliente $cliente
     * @param array $data {
     *   items: [{producto_id, cantidad, precio_unitario, iva?}],
     *   pagos?: [{metodo_pago_id, monto, fecha_pago?, numero_cheque?, fecha_vencimiento?}],
     *   fecha?: date,
     *   tipo_comprobante?: string,
     *   numero_comprobante?: string,
     *   pedido_id?: int
     * }
     * @return Venta
     * @throws ValidationException
     */
    public function ejecutar(Cliente $cliente, array $data): Venta
    {
        return DB::transaction(function () use ($cliente, $data) {
            
            // 1. Bloquear cliente para evitar race conditions
            $cliente = Cliente::lockForUpdate()->findOrFail($cliente->id);

            // 2. Calcular total desde items (backend recalcula, no confía en frontend)
            $total = $this->calcularTotalDesdeItems($data['items']);
            
            // 3. Calcular total de pagos reales (excluyendo CC)
            $totalPagos = $this->calcularTotalPagosReales($data['pagos'] ?? []);
            
            // 4. Calcular saldo pendiente
            $saldoPendiente = round($total - $totalPagos, 2);
            
            // 5. Validar límite de crédito si hay saldo pendiente
            if ($saldoPendiente > 0.01) {
                $this->validarLimiteCredito($cliente, $saldoPendiente);
            }
            
            // 6. Crear venta
            $venta = $this->crearVenta($cliente, $data, $total);
            
            // 7. Crear items de detalle
            $this->crearItems($venta, $data['items']);
            
            // 8. Procesar pagos reales (efectivo, transferencia, cheque)
            $this->procesarPagos($venta, $data['pagos'] ?? []);
            
            // 9. Registrar deuda en CC si hay saldo pendiente
            if ($saldoPendiente > 0.01) {
                $this->cuentaCorrienteService->registrarDeudaPorVenta($venta, $saldoPendiente);
            }
            
            // 10. Actualizar pedido si viene de uno
            if (isset($data['pedido_id']) && $data['pedido_id']) {
                \App\Models\Pedido::where('id', $data['pedido_id'])
                    ->update([
                        'venta_id' => $venta->id,
                        'estado' => 'entregado', // Cambiar estado a entregado
                    ]);
            }
            
            // 11. Calcular y persistir el estado_pago en la base de datos
            // El Accessor calcula el valor correctamente en tiempo real,
            // pero necesitamos guardarlo en la DB para los reportes
            $venta = $venta->fresh(['items', 'pagos', 'cliente', 'cheques']);
            $estadoPagoCalculado = $venta->estado_pago; // Trigger del Accessor
            
            // Actualizar directamente en DB sin trigger de eventos
            DB::table('ventas')
                ->where('id', $venta->id)
                ->update(['estado_pago' => $estadoPagoCalculado]);
            
            // Refrescar modelo con el valor actualizado
            return $venta->fresh(['items', 'pagos', 'cliente', 'cheques']);
        });
    }

    /**
     * Calcula el total de la venta desde los items
     * NUNCA confiar en el total que viene del frontend
     */
    protected function calcularTotalDesdeItems(array $items): float
    {
        $total = 0;
        
        foreach ($items as $item) {
            $cantidad = (float)$item['cantidad'];
            $precio = (float)$item['precio_unitario'];
            $iva = (float)($item['iva'] ?? 0);
            
            $subtotal = $cantidad * $precio * (1 + $iva / 100);
            $total += $subtotal;
        }
        
        return round($total, 2);
    }

    /**
     * Calcula el total de pagos reales (excluyendo Cuenta Corriente y Cheques)
     * 
     * IMPORTANTE: Los cheques NO se consideran pagos reales hasta que se cobran.
     * Por lo tanto, no reducen la deuda en el momento de la venta.
     */
    protected function calcularTotalPagosReales(array $pagos): float
    {
        $total = 0;
        $cuentaCorrienteId = $this->getCuentaCorrienteId();
        $chequeId = $this->getChequeId();
        
        foreach ($pagos as $pago) {
            $metodoPagoId = (int)$pago['metodo_pago_id'];
            
            // Ignorar "Cuenta Corriente" y "Cheque" como pagos reales
            if ($metodoPagoId === $cuentaCorrienteId || $metodoPagoId === $chequeId) {
                continue;
            }
            
            $total += (float)$pago['monto'];
        }
        
        return round($total, 2);
    }

    /**
     * Valida que el cliente no exceda su límite de crédito
     */
    protected function validarLimiteCredito(Cliente $cliente, float $saldoPendiente): void
    {
        // Si no tiene CC habilitada, debe pagar 100% al contado
        if ($cliente->limite_credito <= 0) {
            throw ValidationException::withMessages([
                'saldo' => "El cliente no tiene cuenta corriente habilitada. Debe pagar el total al contado."
            ]);
        }
        
        // Calcular nuevo saldo proyectado
        $saldoActual = $this->cuentaCorrienteService->obtenerSaldoActual($cliente);
        $saldoProyectado = $saldoActual + $saldoPendiente;
        
        // Validar límite
        if ($saldoProyectado > $cliente->limite_credito) {
            throw ValidationException::withMessages([
                'limite_credito' => sprintf(
                    "Excede el límite de crédito. Saldo actual: $%.2f, Nuevo saldo: $%.2f, Límite: $%.2f",
                    $saldoActual,
                    $saldoProyectado,
                    $cliente->limite_credito
                )
            ]);
        }
    }

    /**
     * Crea el registro de venta
     */
    protected function crearVenta(Cliente $cliente, array $data, float $total): Venta
    {
        // Generar número de comprobante automáticamente si se especifica tipo
        $numeroComprobante = $data['numero_comprobante'] ?? null;
        $tipoComprobante = $data['tipo_comprobante'] ?? null;
        
        if ($tipoComprobante && !$numeroComprobante) {
            $numeroComprobante = $this->generarNumeroComprobante($tipoComprobante);
        }
        
        return Venta::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => auth()->id() ?? 1,
            'fecha' => $data['fecha'] ?? now(),
            'total' => $total,
            'tipo_comprobante' => $tipoComprobante,
            'numero_comprobante' => $numeroComprobante,
            'estado_pago' => 'pendiente', // Se actualiza al final
        ]);
    }
    
    /**
     * Genera el próximo número de comprobante para el tipo especificado
     */
    protected function generarNumeroComprobante(string $tipoComprobante): string
    {
        $puntoVenta = '0001'; // Punto de venta por defecto
        
        // Buscar o crear numeración para este tipo de comprobante
        $numeracion = \App\Models\ComprobanteNumeracion::firstOrCreate(
            [
                'tipo_comprobante' => $tipoComprobante,
                'punto_venta' => $puntoVenta,
            ],
            [
                'ultimo_numero' => 0,
            ]
        );
        
        // Incrementar y guardar
        $numeracion->increment('ultimo_numero');
        $numeroConsecutivo = str_pad($numeracion->ultimo_numero, 8, '0', STR_PAD_LEFT);
        
        return "{$puntoVenta}-{$numeroConsecutivo}";
    }

    /**
     * Crea los detalles de venta
     */
    protected function crearItems(Venta $venta, array $items): void
    {
        foreach ($items as $item) {
            DetalleVenta::create([
                'venta_id' => $venta->id,
                'producto_id' => $item['producto_id'],
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $item['precio_unitario'],
                'iva' => $item['iva'] ?? 0,
            ]);
        }
    }

    /**
     * Procesar pagos de la venta
     * 
     * IMPORTANTE: Los cheques se registran en la tabla cheques pero NO se consideran
     * pagos efectivos hasta que se cobran. Solo reducen la deuda cuando estado='cobrado'.
     */
    protected function procesarPagos(Venta $venta, array $pagos): void
    {
        $cuentaCorrienteId = $this->getCuentaCorrienteId();
        
        foreach ($pagos as $pagoData) {
            $metodoPago = MetodoPago::find($pagoData['metodo_pago_id']);
            
            // Crear pago (incluso para Cuenta Corriente y cheques, para tener registro completo)
            $pago = Pago::create([
                'venta_id' => $venta->id,
                'metodo_pago_id' => $pagoData['metodo_pago_id'],
                'monto' => $pagoData['monto'],
                'fecha_pago' => $pagoData['fecha_pago'] ?? now(),
            ]);
            
            // Si es Cuenta Corriente, solo registrar el pago pero la deuda se maneja separado
            if ((int)$pagoData['metodo_pago_id'] === $cuentaCorrienteId) {
                continue;
            }

            // Si es cheque, registrar en tabla cheques (estado=pendiente)
            // El cheque NO reduce deuda hasta que se cobre
            if ($metodoPago && strtolower($metodoPago->nombre) === 'cheque') {
                // BUG 2: Pasar datos directamente sin mapeo
                // ChequeService::buildChequeData se encarga del mapeo unificado
                $this->chequeService->registrarChequeDesdeVenta($venta, array_merge($pagoData, [
                    'pago_id' => $pago->id,
                ]));
            }
        }
    }

    /**
     * Determina el estado de pago de la venta
     * 
     * OBSOLETO: Ahora se calcula automáticamente mediante el Accessor del modelo Venta.
     * Este método se mantiene por compatibilidad pero no se usa en el flujo principal.
     */
    protected function determinarEstadoPago(Venta $venta): string
    {
        $venta->load('pagos.metodoPago', 'cheques');
        
        $total = (float)$venta->total;
        $cuentaCorrienteId = $this->getCuentaCorrienteId();
        $chequeId = $this->getChequeId();
        
        // Sumar solo pagos REALES (no CC, no cheques pendientes)
        $totalPagado = 0;
        
        foreach ($venta->pagos as $pago) {
            $metodoPagoId = (int)$pago->metodo_pago_id;
            
            // Excluir CC
            if ($metodoPagoId === $cuentaCorrienteId) {
                continue;
            }
            
            // Excluir cheques (porque aún no se cobraron)
            if ($metodoPagoId === $chequeId) {
                continue;
            }
            
            $totalPagado += (float)$pago->monto;
        }

        // Tolerancia de 1 centavo para errores de redondeo
        if ($totalPagado >= $total - 0.01) {
            return 'pagado';
        } elseif ($totalPagado > 0.01) {
            return 'parcial';
        } else {
            return 'pendiente';
        }
    }

    /**
     * Obtiene el ID del método de pago Cuenta Corriente
     */
    protected function getCuentaCorrienteId(): ?int
    {
        static $cuentaCorrienteId = null;
        
        if ($cuentaCorrienteId === null) {
            $cuentaCorrienteId = MetodoPago::where('nombre', 'Cuenta Corriente')->value('id');
        }
        
        return $cuentaCorrienteId;
    }

    /**
     * Obtiene el ID del método de pago Cheque
     */
    protected function getChequeId(): ?int
    {
        static $chequeId = null;
        
        if ($chequeId === null) {
            $chequeId = MetodoPago::where('nombre', 'Cheque')->value('id');
        }
        
        return $chequeId;
    }
}
