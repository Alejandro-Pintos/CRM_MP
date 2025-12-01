<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VERIFICACIÓN DE DATOS DEL SISTEMA ===\n\n";

// Usuarios
echo "=== USUARIOS ===\n";
$usuarios = DB::table('usuarios')->get();
foreach ($usuarios as $user) {
    echo "ID: {$user->id}\n";
    echo "Email: {$user->email}\n";
    echo "Nombre: {$user->nombre}\n";
    echo "Created: {$user->created_at}\n\n";
}
echo "Total usuarios: " . $usuarios->count() . "\n\n";

// Roles
echo "=== ROLES ===\n";
$roles = DB::table('roles')->get();
foreach ($roles as $role) {
    echo "- {$role->name}\n";
}
echo "Total roles: " . $roles->count() . "\n\n";

// Permisos del usuario admin
echo "=== PERMISOS DEL ADMIN ===\n";
$adminPermisos = DB::table('model_has_roles')
    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
    ->where('model_has_roles.model_type', 'App\\Models\\Usuario')
    ->where('model_has_roles.model_id', 1)
    ->select('roles.name', 'roles.id')
    ->get();
foreach ($adminPermisos as $rol) {
    echo "- Rol: {$rol->name} (ID: {$rol->id})\n";
}

$permisos = DB::table('permissions')->count();
echo "\nTotal permisos en el sistema: {$permisos}\n\n";

// Métodos de pago
echo "=== MÉTODOS DE PAGO ===\n";
$metodos = DB::table('metodos_pago')->get();
foreach ($metodos as $metodo) {
    echo "ID: {$metodo->id} - {$metodo->nombre}\n";
}
echo "Total métodos de pago: " . $metodos->count() . "\n\n";

// Clientes
echo "=== CLIENTES ===\n";
$clientes = DB::table('clientes')->count();
echo "Total clientes: {$clientes}\n\n";

// Productos
echo "=== PRODUCTOS ===\n";
$productos = DB::table('productos')->count();
echo "Total productos: {$productos}\n";
