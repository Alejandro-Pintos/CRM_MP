<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';
    protected $fillable = ['venta_id','metodo_pago_id','monto','fecha_pago'];

    public function venta() { return $this->belongsTo(Venta::class, 'venta_id'); }
    public function metodo() { return $this->belongsTo(MetodoPago::class, 'metodo_pago_id'); }
}
