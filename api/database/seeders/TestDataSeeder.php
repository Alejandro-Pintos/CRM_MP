<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Carbon\Carbon;
use App\Models\Usuario;

class TestDataSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('es_AR');
        
        // Obtener el usuario admin para asociar ventas
        $adminUser = Usuario::where('email', 'admin@example.com')->first();
        
        if (!$adminUser) {
            $this->command->error('No se encontró el usuario administrador. Ejecuta primero DatabaseSeeder.');
            return;
        }

        // 1. PROVEEDORES (15 proveedores)
        $this->command->info('Creando proveedores...');
        $proveedores = [];
        for ($i = 0; $i < 15; $i++) {
            $proveedores[] = [
                'nombre' => $faker->company,
                'cuit' => $faker->unique()->numerify('20#########'),
                'direccion' => $faker->address,
                'telefono' => $faker->phoneNumber,
                'email' => $faker->companyEmail,
                'estado' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('proveedores')->insert($proveedores);

        // 2. PRODUCTOS (60 productos)
        $this->command->info('Creando productos...');
        $tiposProductos = [
            'Poste de quebracho', 'Tabla de pino', 'Tirante de eucalipto',
            'Viga de madera', 'Machimbre', 'Listón', 'Alfajía',
            'Tablón', 'Tabla de cedro', 'Varilla de hierro'
        ];
        
        $productos = [];
        for ($i = 0; $i < 60; $i++) {
            $productos[] = [
                'codigo' => 'PROD-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'nombre' => $faker->randomElement($tiposProductos) . ' ' . $faker->randomElement(['Premium', 'Standard', 'Económico']),
                'descripcion' => $faker->sentence(6),
                'unidad_medida' => $faker->randomElement(['u', 'm', 'kg']),
                'precio' => $faker->randomFloat(2, 100, 50000),
                'iva' => 21.00,
                'estado' => 'activo',
                'proveedor_id' => $faker->numberBetween(1, 15),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('productos')->insert($productos);

        // 3. CLIENTES (300 clientes)
        $this->command->info('Creando 300 clientes...');
        $provincias = ['Buenos Aires', 'Córdoba', 'Santa Fe', 'Mendoza', 'Tucumán', 'Entre Ríos', 'Salta'];
        
        for ($batch = 0; $batch < 6; $batch++) {
            $clientes = [];
            for ($i = 0; $i < 50; $i++) {
                $fechaRegistro = $faker->dateTimeBetween('-2 years', 'now');
                $clientes[] = [
                    'nombre' => $faker->firstName,
                    'apellido' => $faker->lastName,
                    'email' => $faker->unique()->safeEmail,
                    'telefono' => $faker->phoneNumber,
                    'direccion' => $faker->streetAddress,
                    'ciudad' => $faker->city,
                    'provincia' => $faker->randomElement($provincias),
                    'cuit_cuil' => $faker->optional(0.7)->numerify('20#########'),
                    'fecha_registro' => $fechaRegistro,
                    'estado' => $faker->randomElement(['activo', 'activo', 'activo', 'inactivo']),
                    'saldo_actual' => 0,
                    'limite_credito' => $faker->randomElement([0, 50000, 100000, 200000]),
                    'created_at' => $fechaRegistro,
                    'updated_at' => $fechaRegistro,
                ];
            }
            DB::table('clientes')->insert($clientes);
            $this->command->info('Insertados ' . (($batch + 1) * 50) . ' clientes...');
        }

        // 4. PEDIDOS Y VENTAS
        $this->command->info('Creando pedidos y ventas...');
        $climaEstados = ['Clear', 'Clouds', 'Rain', 'Drizzle', 'Thunderstorm', 'Snow'];
        $climaDescripciones = [
            'Clear' => 'Cielo despejado',
            'Clouds' => 'Parcialmente nublado',
            'Rain' => 'Lluvia moderada',
            'Drizzle' => 'Llovizna',
            'Thunderstorm' => 'Tormenta eléctrica',
            'Snow' => 'Nevadas'
        ];

        $pedidoCount = 0;
        $ventaCount = 0;

        for ($clienteId = 1; $clienteId <= 300; $clienteId++) {
            $numPedidos = $faker->numberBetween(1, 5);
            
            for ($j = 0; $j < $numPedidos; $j++) {
                $fechaPedido = $faker->dateTimeBetween('-6 months', 'now');
                $fechaEntrega = (clone $fechaPedido)->modify('+' . rand(3, 15) . ' days');
                $estado = $faker->randomElement(['pendiente', 'en_proceso', 'entregado', 'entregado', 'cancelado']);
                
                // Datos del clima
                $climaEstado = $faker->randomElement($climaEstados);
                $climaJson = [
                    'main' => $climaEstado,
                    'description' => $climaDescripciones[$climaEstado],
                    'temp' => $faker->randomFloat(1, 5, 35),
                    'humidity' => $faker->numberBetween(30, 90),
                    'wind_speed' => $faker->randomFloat(1, 0, 25)
                ];

                // Crear pedido
                $pedidoId = DB::table('pedidos')->insertGetId([
                    'cliente_id' => $clienteId,
                    'fecha_pedido' => $fechaPedido,
                    'fecha_entrega_aprox' => $fechaEntrega,
                    'estado' => $estado,
                    'direccion_entrega' => $faker->streetAddress,
                    'ciudad_entrega' => $faker->city,
                    'clima_estado' => $climaEstado,
                    'clima_temperatura' => $climaJson['temp'],
                    'clima_humedad' => $climaJson['humidity'],
                    'clima_descripcion' => $climaJson['description'],
                    'clima_json' => json_encode($climaJson),
                    'observaciones' => $faker->optional(0.3)->sentence,
                    'created_at' => $fechaPedido,
                    'updated_at' => $fechaPedido,
                ]);

                // Detalle del pedido
                $numItems = $faker->numberBetween(1, 6);
                $totalPedido = 0;
                $detallesPedido = [];

                for ($k = 0; $k < $numItems; $k++) {
                    $productoId = $faker->numberBetween(1, 60);
                    $producto = DB::table('productos')->where('id', $productoId)->first();
                    $cantidad = $faker->numberBetween(1, 20);
                    $precioUnitario = $producto->precio;
                    $subtotal = $cantidad * $precioUnitario;
                    $totalPedido += $subtotal;

                    $detallesPedido[] = [
                        'pedido_id' => $pedidoId,
                        'producto_id' => $productoId,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precioUnitario,
                        'observaciones' => $faker->optional(0.2)->sentence(3),
                        'created_at' => $fechaPedido,
                        'updated_at' => $fechaPedido,
                    ];
                }

                DB::table('detalle_pedido')->insert($detallesPedido);

                // Si el pedido está entregado, crear venta (80% de probabilidad)
                if ($estado === 'entregado' && $faker->boolean(80)) {
                    $fechaVenta = (clone $fechaEntrega)->modify('+' . rand(0, 3) . ' days');
                    
                    $ventaId = DB::table('ventas')->insertGetId([
                        'cliente_id' => $clienteId,
                        'usuario_id' => $adminUser->id,
                        'fecha' => $fechaVenta,
                        'total' => $totalPedido,
                        'estado_pago' => $faker->randomElement(['pagado', 'pagado', 'parcial', 'pendiente']),
                        'tipo_comprobante' => $faker->randomElement(['Factura A', 'Factura B', 'Remito']),
                        'numero_comprobante' => $faker->numerify('####-########'),
                        'created_at' => $fechaVenta,
                        'updated_at' => $fechaVenta,
                    ]);

                    // Detalle de venta (copiar del pedido)
                    $detallesVenta = [];
                    foreach ($detallesPedido as $detallePedido) {
                        $detallesVenta[] = [
                            'venta_id' => $ventaId,
                            'producto_id' => $detallePedido['producto_id'],
                            'cantidad' => $detallePedido['cantidad'],
                            'precio_unitario' => $detallePedido['precio_unitario'],
                            'created_at' => $fechaVenta,
                            'updated_at' => $fechaVenta,
                        ];
                    }
                    DB::table('detalle_venta')->insert($detallesVenta);

                    // Actualizar pedido con venta_id
                    DB::table('pedidos')->where('id', $pedidoId)->update(['venta_id' => $ventaId]);

                    // Actualizar fecha_ultima_compra del cliente
                    DB::table('clientes')->where('id', $clienteId)->update([
                        'fecha_ultima_compra' => $fechaVenta
                    ]);

                    $ventaCount++;
                }

                $pedidoCount++;
            }

            if ($clienteId % 50 === 0) {
                $this->command->info("Procesados $clienteId clientes - $pedidoCount pedidos - $ventaCount ventas");
            }
        }

        $this->command->info('');
        $this->command->info('====================================');
        $this->command->info('✓ Seed completado exitosamente');
        $this->command->info('====================================');
        $this->command->info("Proveedores: 15");
        $this->command->info("Productos: 60");
        $this->command->info("Clientes: 300");
        $this->command->info("Pedidos: $pedidoCount");
        $this->command->info("Ventas: $ventaCount");
        $this->command->info('====================================');
    }
}
