<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VACIADO DE BASE DE DATOS ===\n\n";
echo "Se mantendrán:\n";
echo "- Usuario admin con todos sus permisos\n";
echo "- Métodos de pago\n";
echo "- Roles y permisos del sistema\n\n";

$confirmacion = readline("¿Estás seguro de que deseas continuar? (escribe 'SI' para confirmar): ");

if (strtoupper(trim($confirmacion)) !== 'SI') {
    echo "\n❌ Operación cancelada.\n";
    exit;
}

echo "\n🔄 Iniciando proceso de limpieza...\n\n";

try {
    DB::beginTransaction();
    
    // Obtener el ID del usuario admin
    $adminUser = DB::table('usuarios')->where('email', 'admin@example.com')->first();
    if (!$adminUser) {
        echo "❌ No se encontró el usuario admin.\n";
        DB::rollBack();
        exit;
    }
    
    $adminId = $adminUser->id;
    echo "✓ Usuario admin encontrado (ID: {$adminId})\n";
    
    // Desactivar verificación de claves foráneas temporalmente
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    
    // 1. Limpiar tablas relacionadas con ventas
    echo "\n📊 Limpiando ventas...\n";
    DB::table('venta_detalles')->truncate();
    DB::table('pagos')->truncate();
    DB::table('ventas')->truncate();
    echo "  ✓ Ventas eliminadas\n";
    
    // 2. Limpiar tablas relacionadas con clientes
    echo "\n👥 Limpiando clientes...\n";
    DB::table('movimientos_cuenta_corriente')->truncate();
    DB::table('cuenta_corriente_clientes')->truncate();
    DB::table('clientes')->truncate();
    echo "  ✓ Clientes eliminados\n";
    
    // 3. Limpiar tablas relacionadas con proveedores
    echo "\n🏢 Limpiando proveedores...\n";
    DB::table('compra_detalles')->truncate();
    DB::table('compras')->truncate();
    DB::table('pagos_proveedores')->truncate();
    DB::table('movimientos_proveedor')->truncate();
    DB::table('cheques_emitidos')->truncate();
    DB::table('proveedores')->truncate();
    echo "  ✓ Proveedores eliminados\n";
    
    // 4. Limpiar productos
    echo "\n📦 Limpiando productos...\n";
    DB::table('productos')->truncate();
    echo "  ✓ Productos eliminados\n";
    
    // 5. Limpiar empleados y sus pagos
    echo "\n👨‍💼 Limpiando empleados...\n";
    DB::table('pago_empleados')->truncate();
    DB::table('empleados')->truncate();
    echo "  ✓ Empleados eliminados\n";
    
    // 6. Limpiar pedidos
    echo "\n📋 Limpiando pedidos...\n";
    DB::table('pedido_detalles')->truncate();
    DB::table('pedidos')->truncate();
    echo "  ✓ Pedidos eliminados\n";
    
    // 7. Limpiar cheques recibidos
    echo "\n💳 Limpiando cheques...\n";
    DB::table('cheques')->truncate();
    echo "  ✓ Cheques eliminados\n";
    
    // 8. Limpiar usuarios excepto el admin
    echo "\n🔐 Limpiando usuarios (excepto admin)...\n";
    $usuariosEliminados = DB::table('usuarios')->where('id', '!=', $adminId)->delete();
    echo "  ✓ {$usuariosEliminados} usuarios eliminados\n";
    
    // 9. Limpiar asignaciones de roles y permisos de usuarios eliminados
    echo "\n🔑 Limpiando asignaciones de roles...\n";
    DB::table('model_has_roles')->where('model_id', '!=', $adminId)->delete();
    DB::table('model_has_permissions')->where('model_id', '!=', $adminId)->delete();
    echo "  ✓ Asignaciones limpiadas\n";
    
    // 10. Limpiar presupuestos si existen
    if (DB::getSchemaBuilder()->hasTable('presupuestos')) {
        echo "\n💵 Limpiando presupuestos...\n";
        DB::table('presupuesto_detalles')->truncate();
        DB::table('presupuestos')->truncate();
        echo "  ✓ Presupuestos eliminados\n";
    }
    
    // Reactivar verificación de claves foráneas
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    
    DB::commit();
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "✅ BASE DE DATOS VACIADA EXITOSAMENTE\n";
    echo str_repeat('=', 60) . "\n\n";
    
    // Mostrar resumen de lo que se mantuvo
    echo "📌 DATOS MANTENIDOS:\n\n";
    
    $metodoPagoCount = DB::table('metodos_pago')->count();
    echo "  • Métodos de pago: {$metodoPagoCount}\n";
    
    $rolesCount = DB::table('roles')->count();
    echo "  • Roles: {$rolesCount}\n";
    
    $permissionsCount = DB::table('permissions')->count();
    echo "  • Permisos: {$permissionsCount}\n";
    
    $adminPermissions = DB::table('model_has_permissions')
        ->where('model_id', $adminId)
        ->where('model_type', 'App\\Models\\Usuario')
        ->count();
    
    $adminRoles = DB::table('model_has_roles')
        ->where('model_id', $adminId)
        ->where('model_type', 'App\\Models\\Usuario')
        ->count();
    
    echo "  • Usuario admin:\n";
    echo "    - Email: {$adminUser->email}\n";
    echo "    - Roles asignados: {$adminRoles}\n";
    echo "    - Permisos directos: {$adminPermissions}\n";
    
    echo "\n✨ La base de datos está lista para nuevos datos.\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "La operación fue revertida. No se realizaron cambios.\n";
}
