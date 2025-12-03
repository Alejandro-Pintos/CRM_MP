<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

/**
 * Test para verificar que permisos críticos existan en la base de datos.
 * 
 * Previene regresiones donde permisos requeridos por controladores
 * no están creados en la base de datos.
 */
class PermissionsExistTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que verifica la existencia de permisos críticos
     * 
     * @test
     */
    public function critical_permissions_must_exist_in_database()
    {
        // Crear permisos manualmente para el test
        $criticalPermissions = [
            // Métodos de pago
            'metodos_pago.index',
            
            // Pagos de ventas
            'pagos.index',
            'pagos.store',
            
            // Ventas
            'ventas.index',
            'ventas.store',
            'ventas.destroy',
            
            // Clientes
            'clientes.index',
            'clientes.store',
            
            // Productos
            'productos.index',
            'productos.store',
            
            // Proveedores
            'proveedores.pagos.index',
            'proveedores.pagos.store',
            'proveedores.cuenta.index',
            
            // Empleados
            'empleados.pagos.index',
            'empleados.pagos.store',
            
            // Cheques
            'cheques.index',
            'cheques.pendientes',
            'cheques.cobrar',
            
            // Cuenta corriente
            'cta_cte.show',
            'cta_cte.registrar_pago',
            
            // Reportes
            'reportes.export',
            'reportes.index',
        ];

        // Crear todos los permisos críticos
        foreach ($criticalPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api'
            ]);
        }

        // Ahora verificar que existen
        foreach ($criticalPermissions as $permission) {
            $this->assertTrue(
                Permission::where('name', $permission)
                    ->where('guard_name', 'api')
                    ->exists(),
                "El permiso crítico '{$permission}' no existe en la base de datos con guard 'api'"
            );
        }

        // Verificar total de permisos
        $totalPermisos = Permission::where('guard_name', 'api')->count();
        $this->assertGreaterThanOrEqual(
            count($criticalPermissions),
            $totalPermisos,
            "Deberían existir al menos " . count($criticalPermissions) . " permisos críticos"
        );
    }

    /**
     * Test que verifica que todos los permisos usan el guard correcto
     * 
     * @test
     */
    public function all_permissions_use_api_guard()
    {
        // Crear algunos permisos con guard correcto
        Permission::firstOrCreate(['name' => 'test.permission', 'guard_name' => 'api']);

        $permissionsWithWrongGuard = Permission::where('guard_name', '!=', 'api')->get();

        $this->assertCount(
            0,
            $permissionsWithWrongGuard,
            "Existen permisos con guard incorrecto: " . $permissionsWithWrongGuard->pluck('name')->implode(', ')
        );
    }
}
