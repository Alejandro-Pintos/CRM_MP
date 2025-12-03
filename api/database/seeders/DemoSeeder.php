<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;
use Carbon\Carbon;

class DemoSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('es_AR');

        // Proveedores
        $proveedores = [];
        for ($i = 0; $i < 15; $i++) {
            $proveedores[] = [
                'nombre' => $faker->company,
                'email' => $faker->companyEmail,
                'telefono' => $faker->phoneNumber,
                'direccion' => $faker->address,
                'cuit' => $faker->unique()->numerify('30#########'), // CUIT argentino simulado
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('proveedores')->insert($proveedores);

        // Productos
        $productos = [];
        for ($i = 0; $i < 60; $i++) {
            $productos[] = [
                'nombre' => $faker->word . ' ' . $faker->colorName,
                'descripcion' => $faker->sentence,
                'precio' => $faker->randomFloat(2, 100, 50000),
                'stock' => $faker->numberBetween(10, 500),
                'proveedor_id' => $faker->numberBetween(1, 15),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('productos')->insert($productos);

        // Clientes
        $clientes = [];
        for ($i = 0; $i < 300; $i++) {
            $clientes[] = [
                'nombre' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'telefono' => $faker->phoneNumber,
                'direccion' => $faker->address,
                'ciudad' => $faker->city,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('clientes')->insert($clientes);

        // Pedidos y Ventas
        $pedidoId = 1;
        $ventaId = 1;
        for ($i = 1; $i <= 300; $i++) {
            $numPedidos = $faker->numberBetween(1, 5);
            for ($j = 0; $j < $numPedidos; $j++) {
                $fechaPedido = $faker->dateTimeBetween('-6 months', 'now');
                $fechaEntrega = (clone $fechaPedido)->modify('+'.rand(2,10).' days');
                $fechaDespacho = (clone $fechaPedido)->modify('+'.rand(1,5).' days');
                $estado = $faker->randomElement(['pendiente', 'en_proceso', 'entregado', 'cancelado']);

                // Simula clima
                $clima = [
                    'estado' => $faker->randomElement(['Clear', 'Clouds', 'Rain', 'Thunderstorm']),
                    'temperatura' => $faker->randomFloat(1, 10, 38),
                    'humedad' => $faker->numberBetween(30, 90),
                    'descripcion' => $faker->sentence(3),
                ];

                $pedido = [
                    'cliente_id' => $i,
                    'fecha_pedido' => $fechaPedido,
                    'fecha_entrega_aprox' => $fechaEntrega,
                    'fecha_despacho' => $fechaDespacho,
                    'estado_clima' => $clima['estado'],
                    'clima_detalle' => json_encode($clima),
                    'estado' => $estado,
                    'created_at' => $fechaPedido,
                    'updated_at' => $fechaPedido,
                ];
                $pedido_id = DB::table('pedidos')->insertGetId($pedido);

                // Detalle de pedido
                $numItems = $faker->numberBetween(1, 5);
                $totalPedido = 0;
                for ($k = 0; $k < $numItems; $k++) {
                    $producto_id = $faker->numberBetween(1, 60);
                    $cantidad = $faker->numberBetween(1, 10);
                    $precio = DB::table('productos')->where('id', $producto_id)->value('precio');
                    $totalPedido += $cantidad * $precio;
                    DB::table('detalle_pedido')->insert([
                        'pedido_id' => $pedido_id,
                        'producto_id' => $producto_id,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio,
                        'observaciones' => $faker->optional()->sentence,
                        'created_at' => $fechaPedido,
                        'updated_at' => $fechaPedido,
                    ]);
                }

                // Simula venta para algunos pedidos entregados
                if ($estado === 'entregado' && $faker->boolean(80)) {
                    $fechaVenta = (clone $fechaEntrega)->modify('+'.rand(0,2).' days');
                    $venta = [
                        'cliente_id' => $i,
                        'fecha_venta' => $fechaVenta,
                        'total' => $totalPedido,
                        'created_at' => $fechaVenta,
                        'updated_at' => $fechaVenta,
                    ];
                    $venta_id = DB::table('ventas')->insertGetId($venta);

                    // Detalle de venta
                    $detallePedido = DB::table('detalle_pedido')->where('pedido_id', $pedido_id)->get();
                    foreach ($detallePedido as $item) {
                        DB::table('detalle_venta')->insert([
                            'venta_id' => $venta_id,
                            'producto_id' => $item->producto_id,
                            'cantidad' => $item->cantidad,
                            'precio_unitario' => $item->precio_unitario,
                            'created_at' => $fechaVenta,
                            'updated_at' => $fechaVenta,
                        ]);
                    }

                    // Relaciona pedido con venta
                    DB::table('pedidos')->where('id', $pedido_id)->update(['venta_id' => $venta_id]);
                }
            }
        }
    }
}