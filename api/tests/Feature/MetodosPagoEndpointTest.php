<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Usuario;
use App\Models\MetodoPago;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Test del endpoint de métodos de pago.
 * 
 * Verifica que el endpoint funciona correctamente con autenticación y permisos.
 */
class MetodosPagoEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configurar permisos y métodos de pago
        $this->artisan('db:seed', ['--class' => 'FixMissingPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'MetodoPagoSeeder']);
    }

    /**
     * Test que usuario autenticado con permiso puede obtener métodos de pago
     * 
     * @test
     */
    public function authenticated_user_with_permission_can_fetch_payment_methods()
    {
        // Crear rol con permiso
        $role = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        
        if (!$role) {
            $role = Role::create(['name' => 'admin', 'guard_name' => 'api']);
            $permission = Permission::where('name', 'metodos_pago.index')->first();
            $role->givePermissionTo($permission);
        }

        // Crear usuario
        $user = Usuario::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        // Login
        $token = auth('api')->login($user);

        // Request
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->getJson('/api/v1/metodos-pago');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'nombre', 'descripcion', 'estado']
            ]);

        $data = $response->json();
        $this->assertGreaterThan(0, count($data), 'Debe devolver al menos un método de pago');
        
        // Verificar que solo devuelve métodos activos
        foreach ($data as $metodo) {
            $this->assertEquals('activo', $metodo['estado']);
        }
    }

    /**
     * Test que usuario sin autenticar no puede acceder
     * 
     * @test
     */
    public function unauthenticated_user_cannot_fetch_payment_methods()
    {
        $response = $this->getJson('/api/v1/metodos-pago');
        
        $response->assertStatus(401);
    }

    /**
     * Test que usuario autenticado sin permiso recibe 403
     * 
     * @test
     */
    public function authenticated_user_without_permission_receives_403()
    {
        // Crear usuario sin permisos
        $user = Usuario::factory()->create();
        $token = auth('api')->login($user);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->getJson('/api/v1/metodos-pago');

        $response->assertStatus(403);
    }

    /**
     * Test que el endpoint solo devuelve métodos activos
     * 
     * @test
     */
    public function endpoint_only_returns_active_payment_methods()
    {
        // Crear método inactivo
        MetodoPago::create([
            'nombre' => 'Método Inactivo',
            'descripcion' => 'Este método no debe aparecer',
            'estado' => 'inactivo',
        ]);

        // Crear usuario con permisos
        $role = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        $user = Usuario::factory()->create();
        $user->assignRole($role);
        $token = auth('api')->login($user);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->getJson('/api/v1/metodos-pago');

        $response->assertStatus(200);

        $data = $response->json();
        
        // Verificar que ningún método inactivo esté en la respuesta
        foreach ($data as $metodo) {
            $this->assertNotEquals('Método Inactivo', $metodo['nombre']);
            $this->assertEquals('activo', $metodo['estado']);
        }
    }
}
