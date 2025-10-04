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
        'precio_unitario',
        'iva',
        'estado',
        'proveedor_id',
    ];

    // Relación con detalles de compras
    public function detalles()
    {
        return $this->hasMany(CompraDetalle::class, 'producto_id');
    }

    // Accesor: precio con IVA ya calculado
    public function getPrecioConIvaAttribute()
    {
        return $this->precio_unitario * (1 + ($this->iva / 100));
    }
}
