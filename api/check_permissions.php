<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Usuario;

echo "=== DIAGNÓSTICO DE PERMISOS ===\n\n";

// 1. Permisos en DB
echo "1. PERMISOS EN BASE DE DATOS:\n";
$permisos = Permission::where('guard_name', 'api')->get(['name', 'guard_name']);
foreach ($permisos as $p) {
    echo "   - {$p->name} (guard: {$p->guard_name})\n";
}
echo "   Total: " . $permisos->count() . " permisos\n\n";

// 2. Roles en DB
echo "2. ROLES EN BASE DE DATOS:\n";
$roles = Role::where('guard_name', 'api')->get(['name', 'guard_name']);
foreach ($roles as $r) {
    echo "   - {$r->name} (guard: {$r->guard_name})\n";
    $rPerms = $r->permissions()->pluck('name');
    echo "     Permisos: " . $rPerms->count() . " -> " . $rPerms->implode(', ') . "\n";
}
echo "\n";

// 3. Usuario admin
echo "3. USUARIO ADMIN:\n";
$admin = Usuario::where('email', 'admin@example.com')->first();
if ($admin) {
    echo "   Email: {$admin->email}\n";
    echo "   Guard del modelo: {$admin->guard_name}\n";
    echo "   Roles: " . $admin->getRoleNames()->implode(', ') . "\n";
    echo "   Permisos directos: " . $admin->getDirectPermissions()->pluck('name')->implode(', ') . "\n";
    echo "   Permisos via roles: " . $admin->getPermissionsViaRoles()->pluck('name')->count() . "\n";
    echo "   TODOS los permisos: " . $admin->getAllPermissions()->pluck('name')->implode(', ') . "\n";
} else {
    echo "   ❌ Usuario admin no encontrado\n";
}

echo "\n=== FIN DIAGNÓSTICO ===\n";
