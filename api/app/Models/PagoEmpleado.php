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

    /**
     * Relación con cheque emitido
     */
    public function cheque()
    {
        return $this->hasOne(Cheque::class, 'pago_empleado_id');
    }

    /**
     * Crear cheque emitido asociado a este pago
     * 
     * @param array $datosRequest
     * @return Cheque
     */
    public function crearChequeEmitido(array $datosRequest)
    {
        return Cheque::create([
            'tipo' => Cheque::TIPO_EMITIDO,
            'pago_empleado_id' => $this->id,
            'empleado_id' => $this->empleado_id,
            'banco' => $datosRequest['banco_cheque'],
            'numero' => $datosRequest['numero_cheque'],
            'monto' => $this->monto,
            'fecha_emision' => $datosRequest['fecha_emision_cheque'],
            'fecha_vencimiento' => $datosRequest['fecha_vencimiento_cheque'] ?? null,
            'estado' => Cheque::ESTADO_PENDIENTE,
            'observaciones' => $datosRequest['observaciones_cheque'] ?? null,
        ]);
    }
}
