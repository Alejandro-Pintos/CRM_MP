<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'telefono',
        'direccion',
        'ciudad',
        'provincia',
        'cuit_cuil',
        'fecha_registro',
        'fecha_ultima_compra',
        'estado',
        'saldo_actual',
        'limite_credito',
        'requiere_factura',
    ];

    /**
     * Casting automático de campos a tipos nativos.
     */
    protected $casts = [
        'fecha_registro' => 'datetime',
        'fecha_ultima_compra' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'saldo_actual' => 'decimal:2',
        'limite_credito' => 'decimal:2',
        'requiere_factura' => 'boolean',
    ];

    // Relación con Compras (se implementará después)
    public function compras()
    {
        return $this->hasMany(Compra::class, 'cliente_id');
    }

    // Relación con Pedidos
    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'cliente_id');
    }

    // Relación con Movimientos de Cuenta Corriente
    public function movimientosCuentaCorriente()
    {
        return $this->hasMany(MovimientoCuentaCorriente::class, 'cliente_id');
    }

    /**
     * Calcula el saldo real del cliente basado en sus movimientos de cuenta corriente.
     * Usa la convención contable estándar: DEBE - HABER
     * 
     * @return float
     */
    public function calcularSaldoReal()
    {
        // Calcular usando DEBE - HABER (convención contable estándar)
        $debe = $this->movimientosCuentaCorriente()
            ->where('tipo', 'venta')
            ->sum('debe'); // Cliente DEBE dinero (ventas a crédito)
        
        $haber = $this->movimientosCuentaCorriente()
            ->whereIn('tipo', ['pago', 'cancelacion'])
            ->sum('haber'); // Cliente HA PAGADO (abonos) + Cancelaciones de deuda
        
        return round($debe - $haber, 2);
    }

    /**
     * Recalcula y actualiza el saldo actual del cliente en la base de datos.
     * 
     * IMPORTANTE: Un saldo negativo indica datos incorrectos (más pagos que ventas).
     * En un sistema de cuenta corriente de ventas, el cliente SIEMPRE es el deudor.
     * 
     * @return bool
     * @throws \Exception Si el saldo calculado es negativo (datos corruptos)
     */
    public function recalcularSaldo()
    {
        $saldoCalculado = $this->calcularSaldoReal();
        
        // VALIDACIÓN CRÍTICA: El saldo NO puede ser negativo
        // Si es negativo, significa que hay más pagos que ventas (datos incorrectos)
        if ($saldoCalculado < -0.01) {
            \Log::error("Cliente #{$this->id} tiene saldo NEGATIVO: {$saldoCalculado}", [
                'cliente_id' => $this->id,
                'nombre' => $this->nombre . ' ' . $this->apellido,
                'saldo_calculado' => $saldoCalculado,
                'debe_total' => $this->movimientosCuentaCorriente()->where('tipo', 'venta')->sum('debe'),
                'haber_total' => $this->movimientosCuentaCorriente()->where('tipo', 'pago')->sum('haber'),
            ]);
            
            throw new \Exception(
                "DATOS CORRUPTOS: Cliente #{$this->id} ({$this->nombre} {$this->apellido}) tiene saldo negativo ({$saldoCalculado}). " .
                "Esto indica que hay más pagos registrados que ventas. " .
                "Ejecuta: php diagnosticar-movimientos.php para identificar movimientos incorrectos."
            );
        }
        
        // Solo actualizar si hay diferencia para evitar updates innecesarios
        if (abs((float)$this->saldo_actual - $saldoCalculado) > 0.01) {
            \Log::info("Recalculando saldo cliente #{$this->id}: {$this->saldo_actual} -> {$saldoCalculado}");
            $this->saldo_actual = $saldoCalculado;
            return $this->save();
        }
        
        return false;
    }

    /**
     * Accesor para obtener siempre el saldo calculado en tiempo real.
     * Esto asegura que el saldo sea siempre preciso, aunque el campo en BD esté desactualizado.
     * 
     * @return float
     */
    public function getSaldoCalculadoAttribute()
    {
        return $this->calcularSaldoReal();
    }

    /**
     * Obtiene el crédito disponible del cliente.
     * Disponible = Límite de crédito - Saldo actual (deuda)
     * 
     * @return float
     */
    public function getCreditoDisponibleAttribute()
    {
        $limite = (float)$this->limite_credito;
        $saldo = $this->calcularSaldoReal();
        return round($limite - $saldo, 2);
    }
}
