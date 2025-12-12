<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'unidad_medida',
        'precio_compra',
        'precio_venta',
        'precio',
        'iva',
        'estado',
        'proveedor_id',
    ];

    protected $appends = ['precio_total'];

    protected $casts = [
        'precio_compra' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'precio' => 'decimal:2',
        'iva' => 'decimal:2',
    ];

    // Relación con detalles de compras
    public function detalles()
    {
        return $this->hasMany(CompraDetalle::class, 'producto_id');
    }

    // Accesor: precio con IVA ya calculado (legacy - mantener por compatibilidad)
    public function getPrecioConIvaAttribute()
    {
        return $this->precio * (1 + ($this->iva / 100));
    }

    /**
     * Calcula el precio total (precio con IVA incluido)
     * El campo 'precio' YA incluye el IVA, por lo tanto precio_total = precio
     */
    public function getPrecioTotalAttribute()
    {
        // El campo 'precio' ya tiene el IVA aplicado, solo lo retornamos formateado
        return round((float)$this->precio, 2);
    }
}
