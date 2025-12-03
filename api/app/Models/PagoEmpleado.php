<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PagoEmpleado extends Model
{
    use HasFactory;

    protected $table = 'pagos_empleados';

    protected $fillable = [
        'empleado_id',
        'fecha_pago',
        'monto',
        'metodo_pago_id',
        'concepto',
        'observaciones',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'monto' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación: Un pago pertenece a un empleado
     */
    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }

    /**
     * Relación: Un pago tiene un método de pago
     */
    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }
}
