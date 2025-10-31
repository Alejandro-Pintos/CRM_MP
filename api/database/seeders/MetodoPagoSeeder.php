<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MetodoPago;

class MetodoPagoSeeder extends Seeder
{
    public function run(): void
    {
        $metodos = [
            ['nombre'=>'Efectivo','descripcion'=>'Pago en efectivo al momento de la entrega','estado'=>'activo'],
            ['nombre'=>'Transferencia Bancaria','descripcion'=>'Transferencia electrónica de fondos','estado'=>'activo'],
            ['nombre'=>'Tarjeta de Débito','descripcion'=>'Pago con tarjeta de débito','estado'=>'activo'],
            ['nombre'=>'Tarjeta de Crédito','descripcion'=>'Pago con tarjeta de crédito','estado'=>'activo'],
            ['nombre'=>'Cheque','descripcion'=>'Pago mediante cheque','estado'=>'activo'],
            ['nombre'=>'Cuenta Corriente','descripcion'=>'Pago a cuenta corriente del cliente','estado'=>'activo'],
            ['nombre'=>'MercadoPago','descripcion'=>'Pago mediante plataforma MercadoPago','estado'=>'activo'],
        ];
        foreach ($metodos as $m) {
            MetodoPago::firstOrCreate(['nombre'=>$m['nombre']], $m);
        }
    }
}
