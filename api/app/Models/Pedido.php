<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'venta_id',
        'fecha_pedido',
        'fecha_entrega_aprox',
        'fecha_despacho',
        'estado',
        'direccion_entrega',
        'ciudad_entrega',
        'clima_estado',
        'clima_temperatura',
        'clima_humedad',
        'clima_descripcion',
        'clima_json',
        'pronostico_extendido',
        'observaciones',
    ];

    protected $casts = [
        'fecha_pedido' => 'datetime',
        'fecha_entrega_aprox' => 'datetime',
        'fecha_despacho' => 'datetime',
        'clima_temperatura' => 'decimal:2',
        'clima_humedad' => 'integer',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DetallePedido::class);
    }

    public function getClimaDataAttribute()
    {
        return $this->clima_json ? json_decode($this->clima_json, true) : null;
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeEnProceso($query)
    {
        return $query->where('estado', 'en_proceso');
    }
}
