<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Limpiar caché de permisos
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

// Definir permisos faltantes de compras y pagos de proveedores
$permisosCompras = [
    'proveedores.compras.index',
    'proveedores.compras.store',
    'proveedores.compras.show',
    'proveedores.compras.destroy',
];

$permisosPagos = [
    'proveedores.pagos.index',
    'proveedores.pagos.store',
    'proveedores.pagos.show',
    'proveedores.pagos.destroy',
];

$todosLosPermisos = array_merge($permisosCompras, $permisosPagos);

echo "Creando permisos faltantes de proveedores...\n";

foreach ($todosLosPermisos as $permiso) {
    $permisoCreado = \Spatie\Permission\Models\Permission::firstOrCreate([
        'name' => $permiso,
        'guard_name' => 'api'
    ]);
    
    echo "✓ Permiso: {$permiso} - " . ($permisoCreado->wasRecentlyCreated ? 'CREADO' : 'YA EXISTÍA') . "\n";
}

// Asignar todos estos permisos al rol admin
$adminRole = \Spatie\Permission\Models\Role::where('guard_name', 'api')
    ->where('name', 'admin')
    ->first();

if ($adminRole) {
    $adminRole->givePermissionTo($todosLosPermisos);
    echo "\n✓ Permisos asignados al rol 'admin'\n";
} else {
    echo "\n✗ No se encontró el rol 'admin' con guard 'api'\n";
}

// Verificar permisos del usuario actual
$usuario = \App\Models\Usuario::first();
if ($usuario) {
    echo "\nPermisos del usuario '{$usuario->email}':\n";
    $permisos = $usuario->getAllPermissions()->pluck('name')->toArray();
    echo "Total de permisos: " . count($permisos) . "\n";
    
    // Verificar si tiene los permisos de compras
    $tieneCompras = $usuario->hasPermissionTo('proveedores.compras.store', 'api');
    echo "Tiene permiso 'proveedores.compras.store': " . ($tieneCompras ? 'SÍ' : 'NO') . "\n";
}

echo "\n✅ Proceso completado.\n";
