<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $table = 'empleados';

    protected $fillable = [
        'nombre','apellido','dni_cuit','telefono','email','direccion',
        'puesto','fecha_ingreso','salario','estado'
    ];
}
