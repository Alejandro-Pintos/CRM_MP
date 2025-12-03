<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cheque extends Model
{
    use HasFactory;

    // Tipos de cheque
    public const TIPO_RECIBIDO = 'recibido';
    public const TIPO_EMITIDO = 'emitido';

    // Constantes de estado
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_COBRADO = 'cobrado';      // Para cheques recibidos
    public const ESTADO_DEBITADO = 'debitado';    // Para cheques emitidos (equivalente a cobrado)
    public const ESTADO_RECHAZADO = 'rechazado';
    public const ESTADO_ANULADO = 'anulado';

    public const ESTADOS_VALIDOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_COBRADO,
        self::ESTADO_DEBITADO,
        self::ESTADO_RECHAZADO,
        self::ESTADO_ANULADO,
    ];

    protected $fillable = [
        'tipo',
        // Cheques recibidos (de clientes)
        'venta_id',
        'cliente_id',
        'pago_id',
        // Cheques emitidos (a proveedores)
        'proveedor_id',
        'pago_proveedor_id',
        // Datos comunes
        'banco',
        'numero',
        'monto',
        'fecha_emision',
        'fecha_vencimiento',
        'estado',
        'fecha_cobro',
        'fecha_rechazo',
        'motivo_rechazo',
        'observaciones',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_cobro' => 'date',
        'fecha_rechazo' => 'date',
        'monto' => 'decimal:2',
    ];

    // Relaciones para cheques RECIBIDOS (de clientes)
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pago()
    {
        return $this->belongsTo(Pago::class);
    }

    // Relaciones para cheques EMITIDOS (a proveedores)
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function pagoProveedor()
    {
        return $this->belongsTo(PagoProveedor::class, 'pago_proveedor_id');
    }

    // Scopes útiles
    public function scopeRecibidos($query)
    {
        return $query->where('tipo', self::TIPO_RECIBIDO);
    }

    public function scopeEmitidos($query)
    {
        return $query->where('tipo', self::TIPO_EMITIDO);
    }
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeCobrados($query)
    {
        return $query->where('estado', 'cobrado');
    }

    public function scopeRechazados($query)
    {
        return $query->where('estado', 'rechazado');
    }

    public function scopeVencidos($query)
    {
        return $query->where('estado', 'pendiente')
                    ->whereNotNull('fecha_vencimiento')
                    ->whereDate('fecha_vencimiento', '<', now());
    }

    public function scopeProximosAVencer($query, int $dias = 7)
    {
        return $query->where('estado', 'pendiente')
                    ->whereNotNull('fecha_vencimiento')
                    ->whereBetween('fecha_vencimiento', [
                        now(),
                        now()->addDays($dias)
                    ]);
    }

    // Accessors
    public function getNumeroFormateadoAttribute()
    {
        return $this->numero ?? 'Sin número';
    }

    public function getClienteNombreAttribute()
    {
        return $this->cliente?->nombre_completo ?? $this->cliente?->nombre;
    }

    public function getVentaNumeroAttribute()
    {
        return $this->venta?->numero_comprobante ?? "Venta #{$this->venta?->id}";
    }

    /**
     * Calcula los días restantes hasta el vencimiento.
     * 
     * @return int|null Positivo si falta, negativo si ya venció, null si no tiene fecha
     */
    public function calcularDiasRestantes(): ?int
    {
        if (!$this->fecha_vencimiento) {
            return null;
        }

        $hoy = now()->startOfDay();
        $vencimiento = \Carbon\Carbon::parse($this->fecha_vencimiento)->startOfDay();

        // diffInDays con false permite valores negativos (si ya venció)
        return $hoy->diffInDays($vencimiento, false);
    }

    /**
     * Verifica si el cheque está vencido.
     * 
     * @return bool
     */
    public function estaVencido(): bool
    {
        if (!$this->fecha_vencimiento || $this->estado !== self::ESTADO_PENDIENTE) {
            return false;
        }

        return now()->startOfDay()->greaterThan(
            \Carbon\Carbon::parse($this->fecha_vencimiento)->startOfDay()
        );
    }

    /**
     * Verifica si el cheque está próximo a vencer.
     * 
     * @param int $dias Cantidad de días de anticipación
     * @return bool
     */
    public function estaProximoAVencer(int $dias = 7): bool
    {
        if (!$this->fecha_vencimiento || $this->estado !== self::ESTADO_PENDIENTE) {
            return false;
        }

        $diasRestantes = $this->calcularDiasRestantes();
        
        return $diasRestantes !== null && $diasRestantes >= 0 && $diasRestantes <= $dias;
    }

    /**
     * Obtiene el estado de alerta del cheque.
     * 
     * @param int $diasAlerta
     * @return string 'vencido'|'alerta'|'normal'|'sin_fecha'
     */
    public function obtenerEstadoAlerta(int $diasAlerta = 7): string
    {
        if ($this->estado !== self::ESTADO_PENDIENTE) {
            return $this->estado;
        }

        if (!$this->fecha_vencimiento) {
            return 'sin_fecha';
        }

        if ($this->estaVencido()) {
            return 'vencido';
        }

        if ($this->estaProximoAVencer($diasAlerta)) {
            return 'alerta';
        }

        return 'normal';
    }

    /**
     * Valida que el cheque tenga las relaciones correctas según su tipo
     * 
     * @return bool
     */
    public function validarRelaciones(): bool
    {
        if ($this->tipo === self::TIPO_RECIBIDO) {
            return !is_null($this->venta_id) && !is_null($this->cliente_id);
        }
        
        if ($this->tipo === self::TIPO_EMITIDO) {
            return !is_null($this->proveedor_id);
        }
        
        return false;
    }

    /**
     * Verifica si el cheque es recibido (de cliente)
     * 
     * @return bool
     */
    public function esRecibido(): bool
    {
        return $this->tipo === self::TIPO_RECIBIDO;
    }

    /**
     * Verifica si el cheque es emitido (a proveedor)
     * 
     * @return bool
     */
    public function esEmitido(): bool
    {
        return $this->tipo === self::TIPO_EMITIDO;
    }
}
