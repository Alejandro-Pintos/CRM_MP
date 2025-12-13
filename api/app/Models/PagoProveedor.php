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

    /**
     * Relación con cheque emitido
     */
    public function cheque()
    {
        return $this->hasOne(Cheque::class, 'pago_proveedor_id');
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
            'pago_proveedor_id' => $this->id,
            'proveedor_id' => $this->proveedor_id,
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
