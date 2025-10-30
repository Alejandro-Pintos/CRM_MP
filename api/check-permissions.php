<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Usuario;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

echo "🔍 Verificando Permisos del Usuario\n";
echo str_repeat("=", 60) . "\n\n";

// Obtener usuario
$user = Usuario::where('email', 'admin@example.com')->first();

if (!$user) {
    echo "❌ Usuario no encontrado\n";
    exit(1);
}

echo "✅ Usuario encontrado: {$user->nombre} ({$user->email})\n\n";

// Verificar roles
$roles = $user->roles;
echo "📋 Roles asignados: " . $roles->count() . "\n";
foreach ($roles as $role) {
    echo "   - {$role->name} (guard: {$role->guard_name})\n";
}
echo "\n";

// Verificar permisos del rol
if ($roles->count() > 0) {
    $role = $roles->first();
    $rolePermissions = $role->permissions;
    echo "🔐 Permisos del rol '{$role->name}': {$rolePermissions->count()}\n";
    foreach ($rolePermissions as $permission) {
        echo "   ✓ {$permission->name}\n";
    }
    echo "\n";
}

// Verificar permiso específico
$hasPermission = $user->hasPermissionTo('ver clientes', 'api');
echo "🎯 ¿Tiene permiso 'ver clientes'? " . ($hasPermission ? "✅ SÍ" : "❌ NO") . "\n\n";

// Listar todos los permisos disponibles
echo "📚 Permisos totales en el sistema: " . Permission::count() . "\n";
$permissions = Permission::where('guard_name', 'api')->get();
foreach ($permissions as $perm) {
    $userHas = $user->hasPermissionTo($perm->name, 'api') ? '✅' : '❌';
    echo "   {$userHas} {$perm->name}\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
