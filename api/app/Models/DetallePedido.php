<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePedido extends Model
{
    protected $table = 'detalle_pedido';

    protected $fillable = [
        'pedido_id',
        'producto_id',
        'cantidad',
        'precio_compra',
        'precio_venta',
        'porcentaje_iva',
        'precio_unitario',
        'observaciones',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_compra' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'porcentaje_iva' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function getSubtotalAttribute()
    {
        return $this->cantidad * $this->precio_unitario;
    }
}
