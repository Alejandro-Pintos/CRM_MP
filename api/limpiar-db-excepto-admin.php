<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== LIMPIEZA DE BASE DE DATOS ===\n";
echo "Se mantendrá: admin@example.com con sus roles y permisos\n";
echo "Se eliminarán: todos los datos de negocio (ventas, clientes, productos, etc.)\n\n";

// Verificar argumento de confirmación
if (!isset($argv[1]) || strtoupper($argv[1]) !== 'SI') {
    echo "❌ Para ejecutar este script use: php limpiar-db-excepto-admin.php SI\n";
    exit(1);
}

echo "✔️  Confirmación recibida. Procediendo con la limpieza...\n";

try {
    // Deshabilitar verificación de foreign keys
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    
    echo "\n🗑️  Iniciando limpieza...\n\n";
    
    // 1. Cheques
    $count = DB::table('cheques')->count();
    DB::table('cheques')->truncate();
    echo "✅ Cheques eliminados: $count\n";
    
    // 2. Movimientos de cuenta corriente
    $count = DB::table('movimientos_cuenta_corriente')->count();
    DB::table('movimientos_cuenta_corriente')->truncate();
    echo "✅ Movimientos CC eliminados: $count\n";
    
    // 3. Pagos
    $count = DB::table('pagos')->count();
    DB::table('pagos')->truncate();
    echo "✅ Pagos eliminados: $count\n";
    
    // 4. Detalles de venta
    $count = DB::table('detalle_venta')->count();
    DB::table('detalle_venta')->truncate();
    echo "✅ Detalles de venta eliminados: $count\n";
    
    // 5. Ventas
    $count = DB::table('ventas')->count();
    DB::table('ventas')->delete(); // Usar delete en lugar de truncate para soft deletes
    echo "✅ Ventas eliminadas: $count\n";
    
    // 6. Detalles de pedido
    $count = DB::table('detalle_pedido')->count();
    DB::table('detalle_pedido')->truncate();
    echo "✅ Detalles de pedido eliminados: $count\n";
    
    // 7. Pedidos
    $count = DB::table('pedidos')->count();
    DB::table('pedidos')->delete();
    echo "✅ Pedidos eliminados: $count\n";
    
    // 8. Resetear saldos de clientes
    $count = DB::table('clientes')->where('email', '!=', 'admin@example.com')->count();
    DB::table('clientes')
        ->where('email', '!=', 'admin@example.com')
        ->update([
            'saldo_actual' => 0,
            'limite_credito' => 0
        ]);
    echo "✅ Saldos de clientes reseteados: $count\n";
    
    // 9. Eliminar clientes (excepto admin si existe como cliente)
    $count = DB::table('clientes')->where('email', '!=', 'admin@example.com')->count();
    DB::table('clientes')->where('email', '!=', 'admin@example.com')->delete();
    echo "✅ Clientes eliminados: $count\n";
    
    // 10. Productos
    $count = DB::table('productos')->count();
    DB::table('productos')->delete();
    echo "✅ Productos eliminados: $count\n";
    
    // 11. Proveedores
    $count = DB::table('proveedores')->count();
    DB::table('proveedores')->delete();
    echo "✅ Proveedores eliminados: $count\n";
    
    // 13. Resetear AUTO_INCREMENT
    echo "\n🔄 Reseteando AUTO_INCREMENT...\n";
    
    $tables = [
        'cheques',
        'movimientos_cuenta_corriente',
        'pagos',
        'detalle_venta',
        'ventas',
        'detalle_pedido',
        'pedidos',
        'clientes',
        'productos',
        'proveedores',
    ];
    
    foreach ($tables as $table) {
        DB::statement("ALTER TABLE $table AUTO_INCREMENT = 1");
    }
    echo "✅ AUTO_INCREMENT reseteado\n";
    
    // Rehabilitar verificación de foreign keys
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    
    echo "\n✅ LIMPIEZA COMPLETADA EXITOSAMENTE\n";
    echo "\n📊 DATOS PRESERVADOS:\n";
    
    $admin = DB::table('usuarios')->where('email', 'admin@example.com')->first();
    if ($admin) {
        echo "  👤 Usuario: {$admin->nombre} ({$admin->email})\n";
        
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_id', $admin->id)
            ->where('model_type', 'App\\Models\\User')
            ->pluck('roles.name');
        
        if ($roles->count() > 0) {
            echo "  🎭 Roles: " . $roles->implode(', ') . "\n";
        }
    }
    
    echo "\n🎉 La base de datos está lista para comenzar desde cero\n";
    
} catch (\Exception $e) {
    // Asegurarse de rehabilitar foreign keys incluso si hay error
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n";
    exit(1);
}
