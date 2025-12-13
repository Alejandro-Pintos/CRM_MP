<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n🧹 Iniciando limpieza de base de datos...\n\n";

// Función helper para limpiar tablas con manejo de errores
function limpiarTabla($tabla, $descripcion) {
    try {
        if (Schema::hasTable($tabla)) {
            $count = DB::table($tabla)->count();
            // Usar delete() en lugar de truncate() para evitar problemas con FK
            DB::table($tabla)->delete();
            echo "✓ {$descripcion} eliminados: {$count}\n";
            return $count;
        } else {
            echo "⊗ Tabla '{$tabla}' no existe (omitida)\n";
            return 0;
        }
    } catch (Exception $e) {
        echo "⚠ Error al limpiar {$tabla}: " . $e->getMessage() . "\n";
        return 0;
    }
}

// Limpiar tablas en orden correcto (hijos primero, padres después)
limpiarTabla('cheques', 'Cheques');
limpiarTabla('pagos_empleados', 'Pagos a empleados');
limpiarTabla('compra_detalles', 'Detalles de compras');
limpiarTabla('detalle_venta', 'Detalles de ventas');
limpiarTabla('detalle_pedido', 'Detalles de pedidos');
limpiarTabla('movimientos_cuenta_corriente', 'Movimientos cuenta corriente');
limpiarTabla('compras', 'Compras');
limpiarTabla('ventas', 'Ventas');
limpiarTabla('pedidos', 'Pedidos');
limpiarTabla('productos', 'Productos');
limpiarTabla('proveedores', 'Proveedores');
limpiarTabla('clientes', 'Clientes');
limpiarTabla('empleados', 'Empleados');

// Verificar que admin y métodos de pago permanecen
try {
    $adminCount = Schema::hasTable('usuarios') ? DB::table('usuarios')->where('email', 'admin@admin.com')->count() : 0;
    $metodosCount = Schema::hasTable('metodos_pago') ? DB::table('metodos_pago')->count() : 0;
    $permisosCount = Schema::hasTable('permission') ? DB::table('permission')->count() : 0;
    $rolesCount = Schema::hasTable('rol') ? DB::table('rol')->count() : 0;
    
    echo "\n✅ Limpieza completada\n";
    echo "📊 Datos conservados:\n";
    echo "  - Usuarios admin: {$adminCount}\n";
    echo "  - Métodos de pago: {$metodosCount}\n";
    echo "  - Permisos: {$permisosCount}\n";
    echo "  - Roles: {$rolesCount}\n";
    echo "\n🎯 Sistema listo para testing\n\n";
} catch (Exception $e) {
    echo "\n✅ Limpieza completada (no se pudo verificar datos conservados)\n";
    echo "🎯 Sistema listo para testing\n\n";
}
