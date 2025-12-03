<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Usuario;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\Cheque;
use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Tests de integración para el endpoint de notificaciones
 */
class NotificationEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected Usuario $usuario;
    protected Cliente $cliente;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario autenticado
        $this->usuario = Usuario::create([
            'nombre' => 'Admin',
            'apellido' => 'Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);

        // Generar token JWT
        $this->token = JWTAuth::fromUser($this->usuario);

        // Crear cliente
        $this->cliente = Cliente::create([
            'nombre' => 'Cliente',
            'apellido' => 'Test',
            'email' => 'cliente@test.com',
            'telefono' => '1234567890',
        ]);
    }

    /** @test */
    public function endpoint_resumen_retorna_contadores_correctos()
    {
        // Crear venta
        $venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'fecha' => now(),
            'total' => 10000,
            'tipo_comprobante' => 'FC',
            'punto_venta' => '00001',
            'numero_comprobante' => '00000001',
        ]);

        // Crear cheque próximo a vencer
        Cheque::create([
            'venta_id' => $venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '12345678',
            'monto' => 5000,
            'fecha_emision' => now(),
            'fecha_vencimiento' => now()->addDays(3),
            'estado' => 'pendiente',
        ]);

        // Crear pedido próximo a entregar
        Pedido::create([
            'cliente_id' => $this->cliente->id,
            'fecha_pedido' => now(),
            'fecha_entrega_aprox' => now()->addDays(2),
            'estado' => 'pendiente',
            'ciudad_entrega' => 'Buenos Aires',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/notificaciones/resumen');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'cheques_proximos_vencer',
                    'cheques_vencidos',
                    'pedidos_proximos_entregar',
                    'pedidos_atrasados',
                ],
            ]);

        $this->assertEquals(1, $response->json('data.cheques_proximos_vencer'));
        $this->assertEquals(0, $response->json('data.cheques_vencidos'));
        $this->assertEquals(1, $response->json('data.pedidos_proximos_entregar'));
        $this->assertEquals(0, $response->json('data.pedidos_atrasados'));
    }

    /** @test */
    public function endpoint_resumen_requiere_autenticacion()
    {
        $response = $this->getJson('/api/v1/notificaciones/resumen');

        $response->assertStatus(401);
    }

    /** @test */
    public function endpoint_listado_retorna_alertas_paginadas()
    {
        // Crear venta
        $venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'fecha' => now(),
            'total' => 10000,
            'tipo_comprobante' => 'FC',
            'punto_venta' => '00001',
            'numero_comprobante' => '00000001',
        ]);

        // Crear múltiples cheques
        for ($i = 1; $i <= 5; $i++) {
            Cheque::create([
                'venta_id' => $venta->id,
                'cliente_id' => $this->cliente->id,
                'numero' => '1000000' . $i,
                'monto' => 1000 * $i,
                'fecha_emision' => now(),
                'fecha_vencimiento' => now()->addDays($i),
                'estado' => 'pendiente',
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/notificaciones?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'tipo',
                        'entidad',
                        'entidad_id',
                        'mensaje',
                        'nivel',
                        'fecha_referencia',
                        'dias_restantes',
                        'datos',
                        'cliente',
                        'venta_id',
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function endpoint_listado_filtra_por_tipo()
    {
        // Crear venta
        $venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'fecha' => now(),
            'total' => 10000,
            'tipo_comprobante' => 'FC',
            'punto_venta' => '00001',
            'numero_comprobante' => '00000001',
        ]);

        // Crear cheque
        Cheque::create([
            'venta_id' => $venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '99999999',
            'monto' => 5000,
            'fecha_emision' => now(),
            'fecha_vencimiento' => now()->addDays(3),
            'estado' => 'pendiente',
        ]);

        // Crear pedido
        Pedido::create([
            'cliente_id' => $this->cliente->id,
            'fecha_pedido' => now(),
            'fecha_entrega_aprox' => now()->addDays(2),
            'estado' => 'pendiente',
            'ciudad_entrega' => 'Buenos Aires',
        ]);

        // Filtrar solo cheques
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/notificaciones?tipo=cheques_proximos_vencer');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('cheques_proximos_vencer', $response->json('data.0.tipo'));
    }

    /** @test */
    public function endpoint_listado_filtra_por_nivel()
    {
        // Crear venta
        $venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'fecha' => now(),
            'total' => 10000,
            'tipo_comprobante' => 'FC',
            'punto_venta' => '00001',
            'numero_comprobante' => '00000001',
        ]);

        // Cheque crítico (vence en 1 día)
        Cheque::create([
            'venta_id' => $venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '88888888',
            'monto' => 5000,
            'fecha_emision' => now(),
            'fecha_vencimiento' => now()->addDay(),
            'estado' => 'pendiente',
        ]);

        // Cheque moderado (vence en 6 días)
        Cheque::create([
            'venta_id' => $venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '77777777',
            'monto' => 3000,
            'fecha_emision' => now(),
            'fecha_vencimiento' => now()->addDays(6),
            'estado' => 'pendiente',
        ]);

        // Filtrar solo críticos
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/notificaciones?nivel=critical');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('critical', $response->json('data.0.nivel'));
    }

    /** @test */
    public function endpoint_limpiar_cache_funciona_correctamente()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/notificaciones/limpiar-cache');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cache de alertas limpiado correctamente',
            ]);
    }

    /** @test */
    public function alertas_incluyen_informacion_del_cliente()
    {
        // Crear venta
        $venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'fecha' => now(),
            'total' => 10000,
            'tipo_comprobante' => 'FC',
            'punto_venta' => '00001',
            'numero_comprobante' => '00000001',
        ]);

        Cheque::create([
            'venta_id' => $venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '55555555',
            'monto' => 5000,
            'fecha_emision' => now(),
            'fecha_vencimiento' => now()->addDays(3),
            'estado' => 'pendiente',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/notificaciones');

        $response->assertStatus(200);
        
        $alert = $response->json('data.0');
        
        $this->assertArrayHasKey('cliente', $alert);
        $this->assertEquals($this->cliente->id, $alert['cliente']['id']);
        $this->assertEquals('Cliente Test', $alert['cliente']['nombre']);
    }

    /** @test */
    public function alertas_de_cheques_incluyen_monto_y_numero()
    {
        // Crear venta
        $venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'fecha' => now(),
            'total' => 10000,
            'tipo_comprobante' => 'FC',
            'punto_venta' => '00001',
            'numero_comprobante' => '00000001',
        ]);

        Cheque::create([
            'venta_id' => $venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '44444444',
            'monto' => 7500.50,
            'fecha_emision' => now(),
            'fecha_vencimiento' => now()->addDays(3),
            'estado' => 'pendiente',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/notificaciones');

        $response->assertStatus(200);
        
        $alert = $response->json('data.0');
        
        $this->assertArrayHasKey('datos', $alert);
        $this->assertEquals('7500.50', $alert['datos']['monto']);
        $this->assertEquals('44444444', $alert['datos']['numero_cheque']);
    }

    /** @test */
    public function alertas_de_pedidos_incluyen_estado_y_ciudad()
    {
        Pedido::create([
            'cliente_id' => $this->cliente->id,
            'fecha_pedido' => now(),
            'fecha_entrega_aprox' => now()->addDays(2),
            'estado' => 'en_proceso',
            'ciudad_entrega' => 'Córdoba',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/notificaciones?tipo=pedidos_proximos_entregar');

        $response->assertStatus(200);
        
        $alert = $response->json('data.0');
        
        $this->assertArrayHasKey('datos', $alert);
        $this->assertEquals('en_proceso', $alert['datos']['estado']);
        $this->assertEquals('Córdoba', $alert['datos']['ciudad_entrega']);
    }
}
