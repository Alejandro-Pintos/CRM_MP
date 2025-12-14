<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== LISTADO DE USUARIOS Y SUS PERMISOS ===\n\n";

$usuarios = \App\Models\Usuario::all();

foreach ($usuarios as $usuario) {
    echo "Usuario: {$usuario->email}\n";
    echo "ID: {$usuario->id}\n";
    echo "Nombre: {$usuario->name}\n";
    
    $roles = $usuario->getRoleNames();
    echo "Roles: " . ($roles->isEmpty() ? 'NINGUNO' : $roles->implode(', ')) . "\n";
    
    $permisos = $usuario->getAllPermissions()->pluck('name');
    echo "Total de permisos: " . $permisos->count() . "\n";
    
    // Verificar permisos específicos de compras
    $tieneComprasStore = $usuario->hasPermissionTo('proveedores.compras.store', 'api');
    echo "Tiene 'proveedores.compras.store': " . ($tieneComprasStore ? '✓ SÍ' : '✗ NO') . "\n";
    
    $tieneComprasIndex = $usuario->hasPermissionTo('proveedores.compras.index', 'api');
    echo "Tiene 'proveedores.compras.index': " . ($tieneComprasIndex ? '✓ SÍ' : '✗ NO') . "\n";
    
    echo "\n" . str_repeat('-', 60) . "\n\n";
}
