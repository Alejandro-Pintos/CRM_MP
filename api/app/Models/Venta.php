<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Venta extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ventas';

    protected $fillable = [
        'cliente_id','usuario_id','fecha','total',
        'estado_pago','tipo_comprobante','numero_comprobante',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    /**
     * Calcular automáticamente el estado de pago basándose en pagos reales
     * 
     * IMPORTANTE: Los cheques NO se consideran pagos efectivos hasta que se cobran.
     * Una venta pagada 100% con cheques pendientes tiene estado "pendiente".
     */
    protected function estadoPago(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                // SIEMPRE cargar las relaciones necesarias
                if (!$this->relationLoaded('pagos')) {
                    $this->load('pagos.metodoPago');
                }
                if (!$this->relationLoaded('cheques')) {
                    $this->load('cheques');
                }

                $total = (float) $this->total;
                
                // Obtener IDs de métodos especiales
                $cuentaCorrienteId = MetodoPago::where('nombre', 'Cuenta Corriente')->value('id');
                $chequeId = MetodoPago::where('nombre', 'Cheque')->value('id');
                
                // Calcular pagos REALES (excluyendo CC y Cheques)
                $totalPagado = 0;
                foreach ($this->pagos as $pago) {
                    $metodoPagoId = (int)$pago->metodo_pago_id;
                    
                    // Excluir Cuenta Corriente
                    if ($metodoPagoId === $cuentaCorrienteId) {
                        continue;
                    }
                    
                    // Excluir Cheques (se consideran en la tabla cheques separada)
                    if ($metodoPagoId === $chequeId) {
                        continue;
                    }
                    
                    $totalPagado += (float)$pago->monto;
                }
                
                // Sumar solo cheques COBRADOS
                $totalChequesCobrados = (float) $this->cheques
                    ->where('estado', Cheque::ESTADO_COBRADO)
                    ->sum('monto');
                
                $totalPagado += $totalChequesCobrados;
                
                // Calcular deuda REAL y ORIGINAL en Cuenta Corriente desde movimientos
                // IMPORTANTE: No solo verificar si hay pagos CC, sino si hay deuda pendiente
                $deudaCCReal = 0;
                $deudaCCOriginal = 0;
                try {
                    $debe = MovimientoCuentaCorriente::where('venta_id', $this->id)
                        ->where('tipo', 'venta')
                        ->sum('debe');
                    
                    $haber = MovimientoCuentaCorriente::where('venta_id', $this->id)
                        ->where('tipo', 'pago')
                        ->sum('haber');
                    
                    $deudaCCOriginal = $debe; // Monto original que fue a CC
                    $deudaCCReal = max(0, $debe - $haber); // Deuda pendiente actual
                } catch (\Throwable $e) {
                    // Fallback: verificar pagos CC
                    $deudaCCReal = $cuentaCorrienteId
                        ? (float) $this->pagos->where('metodo_pago_id', $cuentaCorrienteId)->sum('monto')
                        : 0;
                    $deudaCCOriginal = $deudaCCReal;
                }
                
                // Calcular cheques pendientes o rechazados
                $totalChequesPendientes = (float) $this->cheques
                    ->whereIn('estado', [Cheque::ESTADO_PENDIENTE, Cheque::ESTADO_RECHAZADO])
                    ->sum('monto');

                // LÓGICA:
                // - "pagado": Todo cobrado (sin deuda CC pendiente ni cheques pendientes)
                // - "parcial": Hay deuda CC pendiente o pagos parciales
                // - "pendiente": No hay pagos reales ni cheques cobrados
                
                // Si hay deuda REAL en cuenta corriente, es "parcial"
                if ($deudaCCReal > 0.01) {
                    return 'parcial';
                }
                
                // Si hay cheques pendientes/rechazados, es "pendiente"
                // porque el dinero aún no ingresó
                if ($totalChequesPendientes > 0.01) {
                    return 'pendiente';
                }
                
                // Verificar pagos reales + deuda CC original (incluye lo que ya se pagó a CC)
                $totalCoberturaVenta = $totalPagado + $deudaCCOriginal;
                $saldoSinPagar = round($total - $totalCoberturaVenta, 2);
                
                if ($saldoSinPagar <= 0.01) {
                    return 'pagado';
                } elseif ($totalPagado > 0.01) {
                    return 'parcial';
                } else {
                    return 'pendiente';
                }
            },
            set: fn ($value) => $value // Permite setear manualmente
        );
    }

    public function items()
    {
        return $this->hasMany(DetalleVenta::class, 'venta_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
    public function pagos()
    {
        return $this->hasMany(Pago::class, 'venta_id');
    }

    public function cheques()
    {
        return $this->hasMany(Cheque::class, 'venta_id');
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'venta_id');
    }
}
