<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Crear permisos básicos (usando los nombres que esperan los controladores)
        $permissions = [
            // Clientes
            'clientes.index',
            'clientes.store',
            'clientes.update',
            'clientes.destroy',
            
            // Ventas
            'ventas.index',
            'ventas.store',
            'ventas.update',
            'ventas.destroy',
            
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
            
            // Empleados
            'empleados.index',
            'empleados.store',
            'empleados.update',
            'empleados.destroy',
            
            // Reportes
            'reportes.index',
            'reportes.show',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // Crear rol de administrador
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        
        // Asignar todos los permisos al rol admin
        $adminRole->syncPermissions(Permission::where('guard_name', 'api')->get());

        // Crear usuario administrador
        $admin = Usuario::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'nombre' => 'Administrador',
                'password' => Hash::make('secret123'),
            ]
        );

        // Asignar rol de administrador
        $admin->assignRole($adminRole);

        $this->command->info('✅ Administrador creado exitosamente');
        $this->command->info('📧 Email: admin@example.com');
        $this->command->info('🔑 Password: secret123');
        $this->command->info('🎭 Rol: admin');
        $this->command->info('📝 Permisos: ' . count($permissions) . ' permisos asignados');
    }
}
