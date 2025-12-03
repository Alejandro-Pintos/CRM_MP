<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Cheque;
use App\Models\Pedido;
use App\Models\Cliente;
use App\Models\Venta;
use App\Services\SystemAlertsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests unitarios para el servicio de alertas del sistema
 */
class SystemAlertsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SystemAlertsService $service;
    protected Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SystemAlertsService();

        // Crear cliente de prueba
        $this->cliente = Cliente::create([
            'nombre' => 'Cliente',
            'apellido' => 'Test',
            'email' => 'cliente@test.com',
            'telefono' => '1234567890',
        ]);
    }

    /** @test */
    public function calcula_correctamente_cheques_proximos_vencer()
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

        // Crear cheque que vence en 5 días
        Cheque::create([
            'venta_id' => $venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '12345678',
            'monto' => 5000,
            'fecha_emision' => now(),
            'fecha_vencimiento' => now()->addDays(5),
            'estado' => 'pendiente',
        ]);

        // Crear cheque que vence en 10 días (fuera del umbral de 7)
        Cheque::create([
            'venta_id' => $venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '87654321',
            'monto' => 3000,
            'fecha_emision' => now(),
            'fecha_vencimiento' => now()->addDays(10),
            'estado' => 'pendiente',
        ]);

        $summary = $this->service->getSummary();

        $this->assertEquals(1, $summary['cheques_proximos_vencer']);
    }

    /** @test */
    public function calcula_correctamente_cheques_vencidos()
    {
        // Crear venta
        $venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'fecha' => now()->subDays(10),
            'total' => 10000,
            'tipo_comprobante' => 'FC',
            'punto_venta' => '00001',
            'numero_comprobante' => '00000001',
        ]);

        // Crear cheque vencido hace 3 días
        Cheque::create([
            'venta_id' => $venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '11111111',
            'monto' => 5000,
            'fecha_emision' => now()->subDays(10),
            'fecha_vencimiento' => now()->subDays(3),
            'estado' => 'pendiente',
        ]);

        // Crear cheque cobrado (no debe contarse)
        Cheque::create([
            'venta_id' => $venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '22222222',
            'monto' => 3000,
            'fecha_emision' => now()->subDays(10),
            'fecha_vencimiento' => now()->subDays(5),
            'estado' => 'cobrado',
            'fecha_cobro' => now()->subDays(4),
        ]);

        $summary = $this->service->getSummary();

        $this->assertEquals(1, $summary['cheques_vencidos']);
    }

    /** @test */
    public function calcula_correctamente_pedidos_proximos_entregar()
    {
        // Pedido a entregar en 2 días
        Pedido::create([
            'cliente_id' => $this->cliente->id,
            'fecha_pedido' => now(),
            'fecha_entrega_aprox' => now()->addDays(2),
            'estado' => 'pendiente',
            'ciudad_entrega' => 'Buenos Aires',
        ]);

        // Pedido a entregar en 5 días (fuera del umbral de 3)
        Pedido::create([
            'cliente_id' => $this->cliente->id,
            'fecha_pedido' => now(),
            'fecha_entrega_aprox' => now()->addDays(5),
            'estado' => 'en_proceso',
            'ciudad_entrega' => 'Córdoba',
        ]);

        $summary = $this->service->getSummary();

        $this->assertEquals(1, $summary['pedidos_proximos_entregar']);
    }

    /** @test */
    public function calcula_correctamente_pedidos_atrasados()
    {
        // Pedido atrasado 2 días
        Pedido::create([
            'cliente_id' => $this->cliente->id,
            'fecha_pedido' => now()->subDays(10),
            'fecha_entrega_aprox' => now()->subDays(2),
            'estado' => 'pendiente',
            'ciudad_entrega' => 'Rosario',
        ]);

        // Pedido entregado (no debe contarse)
        Pedido::create([
            'cliente_id' => $this->cliente->id,
            'fecha_pedido' => now()->subDays(10),
            'fecha_entrega_aprox' => now()->subDays(5),
            'estado' => 'entregado',
            'ciudad_entrega' => 'Mendoza',
        ]);

        $summary = $this->service->getSummary();

        $this->assertEquals(1, $summary['pedidos_atrasados']);
    }

    /** @test */
    public function retorna_listado_de_alertas_con_estructura_correcta()
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

        $alerts = $this->service->getAlerts([], 10);

        $this->assertNotEmpty($alerts->items());
        
        $alert = $alerts->items()[0];
        
        // Verificar estructura de la alerta
        $this->assertArrayHasKey('id', $alert);
        $this->assertArrayHasKey('tipo', $alert);
        $this->assertArrayHasKey('entidad', $alert);
        $this->assertArrayHasKey('entidad_id', $alert);
        $this->assertArrayHasKey('mensaje', $alert);
        $this->assertArrayHasKey('nivel', $alert);
        $this->assertArrayHasKey('fecha_referencia', $alert);
        $this->assertArrayHasKey('cliente', $alert);
        $this->assertArrayHasKey('monto', $alert);
    }

    /** @test */
    public function filtra_alertas_por_tipo()
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
            'numero' => '88888888',
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
        $alertsCheques = $this->service->getAlerts(['tipo' => 'cheques_proximos_vencer'], 10);
        $this->assertCount(1, $alertsCheques->items());
        $this->assertEquals('cheques_proximos_vencer', $alertsCheques->items()[0]['tipo']);

        // Filtrar solo pedidos
        $alertsPedidos = $this->service->getAlerts(['tipo' => 'pedidos_proximos_entregar'], 10);
        $this->assertCount(1, $alertsPedidos->items());
        $this->assertEquals('pedidos_proximos_entregar', $alertsPedidos->items()[0]['tipo']);
    }

    /** @test */
    public function calcula_nivel_de_alerta_correctamente()
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
            'numero' => '77777777',
            'monto' => 5000,
            'fecha_emision' => now(),
            'fecha_vencimiento' => now()->addDay(),
            'estado' => 'pendiente',
        ]);

        $alerts = $this->service->getAlerts([], 10);
        $alert = collect($alerts->items())->first();

        $this->assertEquals('critical', $alert['nivel']);
    }

    /** @test */
    public function genera_mensajes_descriptivos()
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
            'numero' => '66666666',
            'monto' => 5000,
            'fecha_emision' => now(),
            'fecha_vencimiento' => now()->addDays(3),
            'estado' => 'pendiente',
        ]);

        $alerts = $this->service->getAlerts([], 10);
        $alert = collect($alerts->items())->first();

        $this->assertStringContainsString('vence en', $alert['mensaje']);
        $this->assertStringContainsString('Cliente Test', $alert['mensaje']);
        $this->assertStringContainsString('66666666', $alert['mensaje']);
    }
}
