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
    ];

    // Relación con Compras (se implementará después)
    public function compras()
    {
        return $this->hasMany(Compra::class, 'cliente_id');
    }
}
