<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Usuario;
use App\Models\Cliente;
use App\Models\Producto;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Test de permisos en VentaController.
 * 
 * Verifica que los endpoints requieren los permisos correctos.
 */
class VentaControllerPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected Usuario $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ejecutar seeders necesarios
        $this->artisan('db:seed', ['--class' => 'FixMissingPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'MetodoPagoSeeder']);
        
        // Crear usuario
        $this->user = Usuario::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    /**
     * Test que index requiere permiso ventas.index
     * 
     * @test
     */
    public function index_requires_ventas_index_permission()
    {
        // Sin permiso → 403
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Accept' => 'application/json',
        ])->getJson('/api/v1/ventas');

        $response->assertStatus(403);

        // Dar permiso
        $permission = Permission::where('name', 'ventas.index')->first();
        $this->user->givePermissionTo($permission);

        // Refrescar token
        $this->token = auth('api')->login($this->user);

        // Con permiso → 200
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Accept' => 'application/json',
        ])->getJson('/api/v1/ventas');

        $response->assertStatus(200);
    }

    /**
     * Test que store requiere permiso ventas.store
     * 
     * @test
     */
    public function store_requires_ventas_store_permission()
    {
        $cliente = Cliente::factory()->create(['limite_credito' => 100000]);
        $producto = Producto::factory()->create(['precio' => 1000]);

        $data = [
            'cliente_id' => $cliente->id,
            'items' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 1,
                    'precio_unitario' => 1000,
                ]
            ],
            'pagos' => [],
        ];

        // Sin permiso → 403
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/ventas', $data);

        $response->assertStatus(403);

        // Dar permiso
        $permission = Permission::where('name', 'ventas.store')->first();
        $this->user->givePermissionTo($permission);

        // Refrescar token
        $this->token = auth('api')->login($this->user);

        // Con permiso → 201 (o 422 si hay validación)
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Accept' => 'application/json',
        ])->postJson('/api/v1/ventas', $data);

        // Debería ser 201 o 422, no 403
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Test que destroy requiere permiso ventas.destroy
     * 
     * @test
     */
    public function destroy_requires_ventas_destroy_permission()
    {
        // Sin permiso → 403
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Accept' => 'application/json',
        ])->deleteJson('/api/v1/ventas/999');

        $response->assertStatus(403);

        // Dar permiso
        $permission = Permission::where('name', 'ventas.destroy')->first();
        $this->user->givePermissionTo($permission);

        // Refrescar token
        $this->token = auth('api')->login($this->user);

        // Con permiso → 404 (venta no existe, pero pasó la verificación de permisos)
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Accept' => 'application/json',
        ])->deleteJson('/api/v1/ventas/999');

        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Test que usuario sin autenticar no puede acceder
     * 
     * @test
     */
    public function unauthenticated_user_cannot_access_ventas_endpoints()
    {
        $response = $this->getJson('/api/v1/ventas');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/ventas', []);
        $response->assertStatus(401);

        $response = $this->deleteJson('/api/v1/ventas/1');
        $response->assertStatus(401);
    }
}
