<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermisosSeeder extends Seeder
{
    public function run(): void
    {
        // Lista de permisos iniciales
        $permisos = [
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

            // Ventas
            'ventas.index',
            'ventas.store',
            'ventas.update',
            'ventas.destroy',

            // Pagos
            'pagos.index',
            'pagos.store',

            // Reportes
            'reportes.export',
        ];

        // Crear permisos si no existen
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(
                ['name' => $permiso, 'guard_name' => 'api']
            );
        }

        // Buscar rol admin (ya creado)
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);

        // Darle todos los permisos al admin
        $admin->givePermissionTo(Permission::all());
    }
}
