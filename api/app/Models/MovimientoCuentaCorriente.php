<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MovimientoCuentaCorriente extends Model
{
    use HasFactory;

    protected $table = 'movimientos_cuenta_corriente';
    protected $fillable = ['cliente_id','tipo','referencia_id','monto','fecha','descripcion'];

    public function cliente() { return $this->belongsTo(Cliente::class, 'cliente_id'); }
}
