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
        // 1) Limpiar caché de permisos (obligatorio al agregar o cambiar permisos)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2) Definir permisos por módulo
        $clientes = ['clientes.index','clientes.store','clientes.update','clientes.destroy'];
        $productos = ['productos.index','productos.store','productos.update','productos.destroy'];
        $proveedores = ['proveedores.index','proveedores.store','proveedores.update','proveedores.destroy'];
        $empleados = ['empleados.index','empleados.store','empleados.update','empleados.destroy'];
        $usuarios = ['usuarios.index','usuarios.store','usuarios.update','usuarios.destroy'];
        $usuariosGestion = ['users.manage', 'users.create', 'users.edit', 'users.delete'];
        $roles = ['roles.index','roles.store','roles.update','roles.destroy'];
        $permisos = ['permisos.index','permisos.store','permisos.update','permisos.destroy'];
        $ventas = ['ventas.index', 'ventas.store', 'ventas.show'];
        $pagos = ['pagos.index','pagos.store'];
        $metodosPago = ['metodos_pago.index'];
        $ctaCte = ['cta_cte.show'];
        $reportes = ['reportes.view'];
        $reportesExport = ['reportes.export'];



        // 3) Crear (si no existen) todos los permisos con guard 'api'
        $TodosLosPermisos = array_merge(
            $clientes, 
            $productos, 
            $proveedores,
            $empleados,
            $usuarios, 
            $usuariosGestion,
            $roles, 
            $permisos, 
            $ventas, 
            $pagos, 
            $metodosPago, 
            $ctaCte, 
            $reportes, 
            $reportesExport
        );
        foreach ($TodosLosPermisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'api']);
        }

        // 4) Crear rol admin (guard 'api') y asignarle TODOS los permisos del guard
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $adminRole->syncPermissions($TodosLosPermisos);

        // 5) Asignar rol admin a un usuario (el primero o el que quieras)
        $adminUser = Usuario::first(); // o: Usuario::where('email','admin@example.com')->first();
        if ($adminUser && !$adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }
    }
}
