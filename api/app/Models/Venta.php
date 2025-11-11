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
     */
    protected function estadoPago(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                // SIEMPRE cargar la relación para calcular el estado real
                if (!$this->relationLoaded('pagos')) {
                    $this->load('pagos');
                }

                // Calcular estado real basado en pagos (excluyendo cuenta corriente)
                $total = (float) $this->total;
                
                // Obtener ID de método "Cuenta Corriente"
                $cuentaCorrienteId = MetodoPago::where('nombre', 'Cuenta Corriente')->value('id');
                
                // Calcular solo pagos reales: sin cuenta corriente Y solo cheques cobrados
                $totalPagado = $cuentaCorrienteId 
                    ? (float) $this->pagos
                        ->where('metodo_pago_id', '!=', $cuentaCorrienteId)
                        ->filter(function($pago) {
                            // Incluir si NO es cheque O si es cheque cobrado
                            return is_null($pago->estado_cheque) || $pago->estado_cheque === 'cobrado';
                        })
                        ->sum('monto')
                    : (float) $this->pagos
                        ->filter(function($pago) {
                            return is_null($pago->estado_cheque) || $pago->estado_cheque === 'cobrado';
                        })
                        ->sum('monto');
                
                // También obtener cuenta corriente (deuda)
                $totalCuentaCorriente = $cuentaCorrienteId
                    ? (float) $this->pagos->where('metodo_pago_id', $cuentaCorrienteId)->sum('monto')
                    : 0;
                
                // Cheques pendientes
                $totalChequesPendientes = (float) $this->pagos
                    ->where('estado_cheque', 'pendiente')
                    ->sum('monto');

                // LÓGICA CORRECTA:
                // - "pagado": Todo el dinero fue recibido (sin deuda en C.C. ni cheques pendientes)
                // - "parcial": Hay pagos, deuda o cheques pendientes
                // - "pendiente": No hay ningún pago ni deuda
                
                // Si hay deuda en cuenta corriente, NUNCA está "pagado"
                if ($totalCuentaCorriente > 0) {
                    return 'parcial'; // Hay deuda pendiente
                }
                
                // Si hay cheques pendientes, tampoco está "pagado"
                if ($totalChequesPendientes > 0) {
                    return 'parcial'; // Hay cheques sin cobrar
                }
                
                // Si no hay deuda ni cheques pendientes, verificar si está pagado en efectivo
                $saldoSinPagar = round($total - $totalPagado, 2);
                
                if ($saldoSinPagar <= 0.01) {
                    return 'pagado'; // Todo pagado y cobrado
                } elseif ($totalPagado > 0) {
                    return 'parcial'; // Pago parcial
                } else {
                    return 'pendiente'; // Sin pagos
                }
            },
            set: fn ($value) => $value // Permite guardar manualmente si es necesario
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

    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'venta_id');
    }
}
