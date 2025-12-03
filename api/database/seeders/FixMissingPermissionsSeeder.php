<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class FixMissingPermissionsSeeder extends Seeder
{
    /**
     * Agrega SOLO los permisos que faltan y que los controladores están verificando.
     * No toca los permisos existentes.
     */
    public function run(): void
    {
        // Permisos faltantes críticos
        $missingPermissions = [
            // Métodos de pago
            'metodos_pago.index',
            
            // Pagos de ventas
            'pagos.index',
            'pagos.store',
            'pagos.update',
            'pagos.destroy',
            
            // Pagos a proveedores
            'proveedores.pagos.index',
            'proveedores.pagos.store',
            'proveedores.pagos.destroy',
            
            // Estado de cuenta proveedores
            'proveedores.cuenta.index',
            
            // Pagos a empleados
            'empleados.pagos.index',
            'empleados.pagos.store',
            'empleados.pagos.destroy',
            
            // Reportes export
            'reportes.export',
            
            // Cheques (ChequeController)
            'cheques.index',
            'cheques.show',
            'cheques.update',
            'cheques.pendientes',
            'cheques.historial',
            'cheques.cobrar',
            'cheques.rechazar',
            
            // Cuenta corriente
            'cta_cte.show',
            'cta_cte.registrar_pago',
            'cta_cte.recalcular',
            
            // Pedidos
            'pedidos.index',
            'pedidos.store',
            'pedidos.show',
            'pedidos.update',
            'pedidos.destroy',
            'pedidos.pendientes',
            'pedidos.asociar_venta',
            
            // Presupuestos
            'presupuestos.enviar_email',
        ];

        $created = 0;
        $existing = 0;

        foreach ($missingPermissions as $permission) {
            $perm = Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'api']
            );
            
            if ($perm->wasRecentlyCreated) {
                $created++;
                $this->command->info("✅ Creado: {$permission}");
            } else {
                $existing++;
            }
        }

        // Asignar TODOS los permisos al rol admin
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        
        if ($adminRole) {
            $allPermissions = Permission::where('guard_name', 'api')->get();
            $adminRole->syncPermissions($allPermissions);
            
            $this->command->info("\n🎭 Rol 'admin' actualizado con {$allPermissions->count()} permisos totales");
        }

        $this->command->info("\n📊 RESUMEN:");
        $this->command->info("   ✅ Permisos creados: {$created}");
        $this->command->info("   ℹ️  Ya existían: {$existing}");
        $this->command->info("   📝 Total de permisos en sistema: " . Permission::where('guard_name', 'api')->count());
    }
}
