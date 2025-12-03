<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Usuario;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Test para verificar que el rol admin tiene todos los permisos críticos.
 * 
 * Previene regresiones donde el rol admin no tiene permisos necesarios.
 */
class AdminRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ejecutar seeder de permisos
        $this->artisan('db:seed', ['--class' => 'FixMissingPermissionsSeeder']);
    }

    /**
     * Test que el rol admin tiene todos los permisos críticos
     * 
     * @test
     */
    public function admin_role_has_all_critical_permissions()
    {
        $criticalPermissions = [
            'ventas.store',
            'ventas.index',
            'pagos.store',
            'pagos.index',
            'metodos_pago.index',
            'cheques.index',
            'cheques.cobrar',
            'cta_cte.show',
            'proveedores.pagos.store',
            'empleados.pagos.store',
        ];

        // Obtener rol admin
        $adminRole = Role::where('name', 'admin')
            ->where('guard_name', 'api')
            ->first();

        $this->assertNotNull($adminRole, "El rol 'admin' debe existir");

        // Verificar que tiene TODOS los permisos críticos
        foreach ($criticalPermissions as $perm) {
            $hasPermission = $adminRole->hasPermissionTo($perm, 'api');
            
            $this->assertTrue(
                $hasPermission,
                "El rol admin no tiene el permiso crítico '{$perm}'"
            );
        }
    }

    /**
     * Test que usuario con rol admin puede realizar acciones críticas
     * 
     * @test
     */
    public function user_with_admin_role_can_perform_critical_actions()
    {
        // Crear usuario admin
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        
        $admin = Usuario::factory()->create();
        $admin->assignRole($adminRole);

        // Verificar permisos individuales
        $this->assertTrue($admin->can('ventas.store', 'api'));
        $this->assertTrue($admin->can('pagos.store', 'api'));
        $this->assertTrue($admin->can('metodos_pago.index', 'api'));
        $this->assertTrue($admin->can('cheques.cobrar', 'api'));
    }

    /**
     * Test que el rol admin tiene al menos 50 permisos
     * (considerando todos los módulos del sistema)
     * 
     * @test
     */
    public function admin_role_has_sufficient_permissions()
    {
        $adminRole = Role::where('name', 'admin')
            ->where('guard_name', 'api')
            ->first();

        $this->assertNotNull($adminRole);

        $permissionsCount = $adminRole->permissions()->count();

        $this->assertGreaterThanOrEqual(
            50,
            $permissionsCount,
            "El rol admin debería tener al menos 50 permisos. Tiene: {$permissionsCount}"
        );
    }

    /**
     * Test que usuario sin rol admin NO tiene permisos críticos
     * 
     * @test
     */
    public function user_without_admin_role_does_not_have_critical_permissions()
    {
        $user = Usuario::factory()->create();

        $this->assertFalse($user->can('ventas.store'));
        $this->assertFalse($user->can('pagos.store'));
        $this->assertFalse($user->can('metodos_pago.index'));
    }
}
