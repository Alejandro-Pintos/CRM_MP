<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre','cuit','direccion','telefono','email','estado'
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación con compras
     */
    public function compras()
    {
        return $this->hasMany(Compra::class);
    }

    /**
     * Relación con pagos
     */
    public function pagos()
    {
        return $this->hasMany(PagoProveedor::class);
    }

    /**
     * Scope para proveedores activos
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Scope para proveedores inactivos
     */
    public function scopeInactivos($query)
    {
        return $query->where('estado', 'inactivo');
    }
}
