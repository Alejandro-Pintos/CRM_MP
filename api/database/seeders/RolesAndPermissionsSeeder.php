<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Usuario;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar caché de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ==== Permisos de Clientes ====
        $clientes = [
            'clientes.index',
            'clientes.store',
            'clientes.update',
            'clientes.destroy',
        ];

        // ==== Permisos de Productos ====
        $productos = [
            'productos.index',
            'productos.store',
            'productos.update',
            'productos.destroy',
        ];

        $permisos = array_merge($clientes, $productos);

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(
                ['name' => $permiso, 'guard_name' => 'api']
            );
        }

        // ==== Rol Admin ====
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api']
        );

        $adminRole->syncPermissions($permisos);

        // ==== Asignar rol admin al primer usuario ====
        $adminUser = Usuario::first();
        if ($adminUser) {
            $adminUser->assignRole($adminRole);
        }
    }
}
