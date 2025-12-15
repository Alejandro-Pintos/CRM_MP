<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearDatabaseCommand extends Command
{
    protected $signature = 'db:clear {--force : Forzar sin confirmación}';
    protected $description = 'Vaciar la base de datos manteniendo solo el admin y los métodos de pago';

    public function handle()
    {
        $this->info('=== VACIADO DE BASE DE DATOS ===');
        $this->newLine();
        $this->line('Se mantendrán:');
        $this->line('- Usuario admin con todos sus permisos');
        $this->line('- Métodos de pago');
        $this->line('- Roles y permisos del sistema');
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('¿Estás seguro de que deseas continuar?', false)) {
            $this->error('Operación cancelada.');
            return 1;
        }

        $this->info('🔄 Iniciando proceso de limpieza...');
        $this->newLine();

        try {
            // Obtener el ID del usuario admin
            $adminUser = DB::table('usuarios')->where('email', 'admin@example.com')->first();
            if (!$adminUser) {
                $this->error('No se encontró el usuario admin.');
                return 1;
            }
            
            $adminId = $adminUser->id;
            $this->info("✓ Usuario admin encontrado (ID: {$adminId})");
            
            // Desactivar verificación de claves foráneas temporalmente
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            $schema = DB::getSchemaBuilder();
            
            // 1. Limpiar tablas relacionadas con ventas
            $this->line('📊 Limpiando ventas...');
            if ($schema->hasTable('venta_detalles')) DB::table('venta_detalles')->truncate();
            if ($schema->hasTable('ventas_detalles')) DB::table('ventas_detalles')->truncate();
            if ($schema->hasTable('pagos')) DB::table('pagos')->truncate();
            if ($schema->hasTable('ventas')) DB::table('ventas')->truncate();
            $this->line('  ✓ Ventas eliminadas');
            
            // 2. Limpiar tablas relacionadas con clientes
            $this->line('👥 Limpiando clientes...');
            if ($schema->hasTable('movimientos_cuenta_corriente')) DB::table('movimientos_cuenta_corriente')->truncate();
            if ($schema->hasTable('cuenta_corriente_clientes')) DB::table('cuenta_corriente_clientes')->truncate();
            if ($schema->hasTable('clientes')) DB::table('clientes')->truncate();
            $this->line('  ✓ Clientes eliminados');
            
            // 3. Limpiar tablas relacionadas con proveedores
            $this->line('🏢 Limpiando proveedores...');
            if ($schema->hasTable('compra_detalles')) DB::table('compra_detalles')->truncate();
            if ($schema->hasTable('compras_detalles')) DB::table('compras_detalles')->truncate();
            if ($schema->hasTable('compras')) DB::table('compras')->truncate();
            if ($schema->hasTable('pagos_proveedores')) DB::table('pagos_proveedores')->truncate();
            if ($schema->hasTable('movimientos_proveedor')) DB::table('movimientos_proveedor')->truncate();
            if ($schema->hasTable('cheques_emitidos')) DB::table('cheques_emitidos')->truncate();
            if ($schema->hasTable('proveedores')) DB::table('proveedores')->truncate();
            $this->line('  ✓ Proveedores eliminados');
            
            // 4. Limpiar productos
            $this->line('📦 Limpiando productos...');
            if ($schema->hasTable('productos')) DB::table('productos')->truncate();
            $this->line('  ✓ Productos eliminados');
            
            // 5. Limpiar empleados y sus pagos
            $this->line('👨‍💼 Limpiando empleados...');
            if ($schema->hasTable('pago_empleados')) DB::table('pago_empleados')->truncate();
            if ($schema->hasTable('empleados')) DB::table('empleados')->truncate();
            $this->line('  ✓ Empleados eliminados');
            
            // 6. Limpiar pedidos
            $this->line('📋 Limpiando pedidos...');
            if ($schema->hasTable('pedido_detalles')) DB::table('pedido_detalles')->truncate();
            if ($schema->hasTable('pedidos_detalles')) DB::table('pedidos_detalles')->truncate();
            if ($schema->hasTable('pedidos')) DB::table('pedidos')->truncate();
            $this->line('  ✓ Pedidos eliminados');
            
            // 7. Limpiar cheques recibidos
            $this->line('💳 Limpiando cheques...');
            if ($schema->hasTable('cheques')) DB::table('cheques')->truncate();
            $this->line('  ✓ Cheques eliminados');
            
            // 8. Limpiar usuarios excepto el admin
            $this->line('🔐 Limpiando usuarios (excepto admin)...');
            $usuariosEliminados = DB::table('usuarios')->where('id', '!=', $adminId)->delete();
            $this->line("  ✓ {$usuariosEliminados} usuarios eliminados");
            
            // 9. Limpiar asignaciones de roles y permisos de usuarios eliminados
            $this->line('🔑 Limpiando asignaciones de roles...');
            DB::table('model_has_roles')->where('model_id', '!=', $adminId)->delete();
            DB::table('model_has_permissions')->where('model_id', '!=', $adminId)->delete();
            $this->line('  ✓ Asignaciones limpiadas');
            
            // 10. Limpiar presupuestos si existen
            if ($schema->hasTable('presupuestos')) {
                $this->line('💵 Limpiando presupuestos...');
                if ($schema->hasTable('presupuesto_detalles')) DB::table('presupuesto_detalles')->truncate();
                if ($schema->hasTable('presupuestos_detalles')) DB::table('presupuestos_detalles')->truncate();
                DB::table('presupuestos')->truncate();
                $this->line('  ✓ Presupuestos eliminados');
            }
            
            // Reactivar verificación de claves foráneas
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            
            $this->newLine();
            $this->info('✅ BASE DE DATOS VACIADA EXITOSAMENTE');
            $this->newLine();
            
            // Mostrar resumen
            $this->line('📌 DATOS MANTENIDOS:');
            $this->newLine();
            
            $metodoPagoCount = DB::table('metodos_pago')->count();
            $this->line("  • Métodos de pago: {$metodoPagoCount}");
            
            $rolesCount = DB::table('roles')->count();
            $this->line("  • Roles: {$rolesCount}");
            
            $permissionsCount = DB::table('permissions')->count();
            $this->line("  • Permisos: {$permissionsCount}");
            
            $this->line("  • Usuario admin: {$adminUser->email}");
            
            $this->newLine();
            $this->info('✨ La base de datos está lista para nuevos datos.');
            
            return 0;
            
        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->error('ERROR: ' . $e->getMessage());
            return 1;
        }
    }
}
