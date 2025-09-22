<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Venta extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ventas';

    protected $fillable = [
        'cliente_id','usuario_id','fecha','total',
        'estado_pago','tipo_comprobante','numero_comprobante',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(DetalleVenta::class, 'venta_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id'); // asumiendo App\Models\Cliente existe
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
