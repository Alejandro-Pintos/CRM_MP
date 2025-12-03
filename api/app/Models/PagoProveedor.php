<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagoProveedor extends Model
{
    use HasFactory;

    protected $table = 'pagos_proveedores';

    protected $fillable = [
        'proveedor_id',
        'fecha_pago',
        'monto',
        'metodo_pago_id',
        'referencia',
        'concepto',
        'observaciones',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'monto' => 'decimal:2',
    ];

    /**
     * Relación con proveedor
     */
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * Relación con método de pago
     */
    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    /**
     * Relación con usuario que registró el pago
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
