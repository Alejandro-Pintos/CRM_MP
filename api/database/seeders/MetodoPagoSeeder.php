<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MetodoPago;

class MetodoPagoSeeder extends Seeder
{
    public function run(): void
    {
        $metodos = [
            ['nombre'=>'Efectivo','descripcion'=>null,'estado'=>'activo'],
            ['nombre'=>'Transferencia','descripcion'=>null,'estado'=>'activo'],
            ['nombre'=>'Tarjeta','descripcion'=>null,'estado'=>'activo'],
        ];
        foreach ($metodos as $m) {
            MetodoPago::firstOrCreate(['nombre'=>$m['nombre']], $m);
        }
    }
}
