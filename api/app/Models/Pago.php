<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';
    protected $fillable = [
        'venta_id',
        'metodo_pago_id',
        'monto',
        'fecha_pago',
        'estado_cheque',
        'numero_cheque',
        'fecha_cheque',
        'fecha_cobro',
        'observaciones_cheque'
    ];

    protected $casts = [
        'fecha_pago' => 'datetime',
        'fecha_cheque' => 'date',
        'fecha_cobro' => 'date',
        'monto' => 'decimal:2',
    ];

    public function venta() { return $this->belongsTo(Venta::class, 'venta_id'); }
    public function metodo() { return $this->belongsTo(MetodoPago::class, 'metodo_pago_id'); }
    public function metodoPago() { return $this->belongsTo(MetodoPago::class, 'metodo_pago_id'); }
    public function cheque() { return $this->hasOne(Cheque::class, 'pago_id'); }
}
