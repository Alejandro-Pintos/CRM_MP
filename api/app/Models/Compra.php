<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Compra extends Model
{
    use SoftDeletes;

    protected $table = 'compras';

    protected $fillable = [
        'proveedor_id',
        'fecha_compra',
        'estado',
        'metodo_pago',
        'subtotal',
        'descuento_global',
        'impuestos_total',
        'monto_total',
        'observaciones',
    ];

    protected $casts = [
        'fecha_compra' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento_global' => 'decimal:2',
        'impuestos_total' => 'decimal:2',
        'monto_total' => 'decimal:2',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function detalles()
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }
}
