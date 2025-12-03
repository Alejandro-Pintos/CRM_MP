<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Empleado extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'empleados';

    protected $fillable = [
        'nombre_completo',
        'documento',
        'telefono',
        'email',
        'direccion',
        'puesto',
        'notas',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación: Un empleado tiene muchos pagos
     */
    public function pagos()
    {
        return $this->hasMany(PagoEmpleado::class, 'empleado_id');
    }

    /**
     * Scope: solo empleados activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: solo empleados inactivos
     */
    public function scopeInactivos($query)
    {
        return $query->where('activo', false);
    }
}
