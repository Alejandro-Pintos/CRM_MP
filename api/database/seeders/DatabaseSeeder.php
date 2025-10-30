<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear permisos con los nombres que esperan los controladores
        $permissions = [
            // Clientes
            'clientes.index',
            'clientes.store',
            'clientes.update',
            'clientes.destroy',
            // Productos
            'productos.index',
            'productos.store',
            'productos.update',
            'productos.destroy',
            // Proveedores
            'proveedores.index',
            'proveedores.store',
            'proveedores.update',
            'proveedores.destroy',
            // Ventas
            'ventas.index',
            'ventas.store',
            'ventas.update',
            'ventas.destroy',
            // Pedidos
            'pedidos.index',
            'pedidos.store',
            'pedidos.update',
            'pedidos.destroy',
            // Métodos de Pago
            'metodos_pago.index',
            // Pagos
            'pagos.index',
            'pagos.store',
            // Cuenta Corriente
            'cta_cte.show',
            // Reportes
            'reportes.index',
            'reportes.clientes',
            'reportes.productos',
            'reportes.proveedores',
            'reportes.ventas',
            'reportes.export',
            // Dashboard
            'dashboard.index',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // Crear rol de administrador
        $adminRole = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'api']);
        
        // Asignar todos los permisos al rol de administrador
        $adminRole->syncPermissions(Permission::all());

        // Crear usuario administrador
        $admin = Usuario::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'nombre' => 'Administrador',
                'password' => bcrypt('secret123'),
            ]
        );

        // Asignar rol de administrador al usuario
        $admin->assignRole($adminRole);

        // Llamar al seeder de datos de prueba
        $this->call(TestDataSeeder::class);
    }
}
