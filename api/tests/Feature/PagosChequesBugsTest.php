<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Usuario;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\MetodoPago;
use App\Models\Cheque;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests para verificar que los BUGS de pagos con cheque están solucionados.
 * 
 * BUG 1: Resumen de pagos no mostraba correctamente el total de cheques
 * BUG 2: Cheques creados no guardaban las fechas correctamente
 */
class PagosChequesBugsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cliente;
    protected $producto;
    protected $metodoCheque;
    protected $metodoCuentaCorriente;
    protected $metodoEfectivo;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear permisos y rol
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'api']);
        Permission::create(['name' => 'ventas.store', 'guard_name' => 'api']);
        Permission::create(['name' => 'ventas.index', 'guard_name' => 'api']);
        Permission::create(['name' => 'pagos.index', 'guard_name' => 'api']);
        Permission::create(['name' => 'cheques.index', 'guard_name' => 'api']);
        $adminRole->givePermissionTo(['ventas.store', 'ventas.index', 'pagos.index', 'cheques.index']);

        // Crear usuario autenticado
        $this->user = Usuario::create([
            'nombre' => 'Test User',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->user->assignRole($adminRole);
        $this->actingAs($this->user, 'api');

        // Crear datos de prueba
        $this->cliente = Cliente::create([
            'nombre' => 'Cliente',
            'apellido' => 'Test',
            'email' => 'cliente@test.com',
            'limite_credito' => 10000,
            'saldo_actual' => 0,
        ]);

        $this->producto = Producto::create([
            'codigo' => 'TEST001',
            'nombre' => 'Producto Test',
            'unidad_medida' => 'UN',
            'precio_compra' => 500,
            'precio_venta' => 1000,
            'precio' => 1000,
            'iva' => 0,
            'estado' => 'activo',
        ]);

        // Crear métodos de pago
        $this->metodoCheque = MetodoPago::firstOrCreate(['nombre' => 'Cheque']);
        $this->metodoCuentaCorriente = MetodoPago::firstOrCreate(['nombre' => 'Cuenta Corriente']);
        $this->metodoEfectivo = MetodoPago::firstOrCreate(['nombre' => 'Efectivo']);
    }

    /**
     * BUG 1: Test que verifica que el resumen de pagos calcula correctamente el total de cheques.
     * 
     * ANTES: Una venta pagada 100% con cheque mostraba "Cheques $0,00"
     * DESPUÉS: Debe mostrar el total del cheque en el campo correspondiente
     */
    public function test_resumen_pagos_venta_muestra_total_cheques_correctamente()
    {
        // ARRANGE: Crear venta con pago 100% con cheque
        $fechaCobro = now()->addDays(30)->format('Y-m-d');
        
        $response = $this->postJson('/api/v1/ventas', [
            'cliente_id' => $this->cliente->id,
            'fecha' => now()->format('Y-m-d'),
            'items' => [[
                'producto_id' => $this->producto->id,
                'cantidad' => 1,
                'precio_unitario' => 1000,
            ]],
            'pagos' => [[
                'metodo_pago_id' => $this->metodoCheque->id,
                'monto' => 1000,
                'numero_cheque' => '12345678',
                'fecha_cheque' => now()->format('Y-m-d'),
                'fecha_cobro' => $fechaCobro, // BUG 2: Este campo debe guardarse
            ]],
        ]);

        $response->assertStatus(201);
        $venta = Venta::latest()->first();

        // ACT: Obtener resumen de pagos
        $resumen = $this->getJson("/api/v1/ventas/{$venta->id}/pagos/resumen");

        // ASSERT BUG 1: El total de cheques debe ser 1000, NO 0
        $resumen->assertStatus(200);
        $resumen->assertJson([
            'total_venta' => 1000.0,
            'total_cobrado' => 0.0, // Cheques no se consideran cobrados hasta que se procesan
            'total_cheques' => 1000.0, // ✅ BUG 1 SOLUCIONADO
            'total_deuda_cc' => 0.0,
            'saldo_pendiente' => 0.0, // Está cubierto por el cheque
        ]);
    }

    /**
     * BUG 2: Test que verifica que los cheques creados guardan todas las fechas correctamente.
     * 
     * ANTES: Cheques mostraban "Sin fecha" en días restantes y estado
     * DESPUÉS: Deben tener fecha_vencimiento y calcular días restantes correctamente
     */
    public function test_cheque_creado_desde_venta_guarda_fechas_correctamente()
    {
        // ARRANGE
        $fechaEmision = now()->format('Y-m-d');
        $fechaCobro = now()->addDays(30)->format('Y-m-d');

        // ACT: Crear venta con cheque
        $response = $this->postJson('/api/v1/ventas', [
            'cliente_id' => $this->cliente->id,
            'fecha' => now()->format('Y-m-d'),
            'items' => [[
                'producto_id' => $this->producto->id,
                'cantidad' => 1,
                'precio_unitario' => 1000,
            ]],
            'pagos' => [[
                'metodo_pago_id' => $this->metodoCheque->id,
                'monto' => 1000,
                'numero_cheque' => '87654321',
                'fecha_cheque' => $fechaEmision,
                'fecha_cobro' => $fechaCobro, // BUG 2: Debe guardarse como fecha_vencimiento
                'observaciones_cheque' => 'Cheque de prueba',
            ]],
        ]);

        $response->assertStatus(201);

        // ASSERT BUG 2: El cheque debe tener todas las fechas guardadas
        $cheque = Cheque::latest()->first();
        
        $this->assertNotNull($cheque, 'El cheque debe haberse creado');
        $this->assertEquals('87654321', $cheque->numero);
        $this->assertEquals($fechaEmision, $cheque->fecha_emision->format('Y-m-d'));
        
        // ✅ BUG 2 SOLUCIONADO: fecha_cobro del frontend se guarda como fecha_vencimiento
        $this->assertEquals($fechaCobro, $cheque->fecha_vencimiento->format('Y-m-d'));
        $this->assertNotNull($cheque->fecha_vencimiento, 'fecha_vencimiento NO debe ser NULL');
        
        // Verificar que se calculan los días restantes
        $diasRestantes = $cheque->calcularDiasRestantes();
        $this->assertNotNull($diasRestantes, 'dias_restantes debe ser calculable');
        $this->assertEquals(30, $diasRestantes);
        
        // Verificar que el estado de alerta NO es 'sin_fecha'
        $estadoAlerta = $cheque->obtenerEstadoAlerta();
        $this->assertNotEquals('sin_fecha', $estadoAlerta);
        $this->assertEquals('normal', $estadoAlerta);
    }

    /**
     * Test que verifica el flujo completo: crear venta con cheque y verificar seguimiento.
     */
    public function test_flujo_completo_venta_con_cheque_aparece_en_seguimiento()
    {
        // ARRANGE
        $fechaCobro = now()->addDays(15)->format('Y-m-d');

        // ACT: Crear venta con cheque
        $this->postJson('/api/v1/ventas', [
            'cliente_id' => $this->cliente->id,
            'fecha' => now()->format('Y-m-d'),
            'items' => [[
                'producto_id' => $this->producto->id,
                'cantidad' => 2,
                'precio_unitario' => 500,
            ]],
            'pagos' => [[
                'metodo_pago_id' => $this->metodoCheque->id,
                'monto' => 1000,
                'numero_cheque' => '11223344',
                'fecha_cheque' => now()->format('Y-m-d'),
                'fecha_cobro' => $fechaCobro,
                'observaciones_cheque' => 'Pago a 15 días',
            ]],
        ]);

        // ASSERT: Obtener seguimiento de cheques
        $seguimiento = $this->getJson('/api/v1/cheques');
        $seguimiento->assertStatus(200);

        $cheque = collect($seguimiento->json('cheques'))->firstWhere('numero_cheque', '11223344');
        
        $this->assertNotNull($cheque, 'El cheque debe aparecer en seguimiento');
        
        // ✅ BUG 2 SOLUCIONADO: Todos los datos deben estar presentes
        $this->assertEquals('11223344', $cheque['numero_cheque']);
        $this->assertEquals(1000, $cheque['monto']);
        $this->assertEquals($fechaCobro, $cheque['fecha_cobro']); // fecha_vencimiento mapeada a fecha_cobro
        $this->assertNotNull($cheque['dias_restantes']);
        $this->assertEquals(15, $cheque['dias_restantes']);
        $this->assertEquals('normal', $cheque['estado_alerta']);
        $this->assertNotEquals('sin_fecha', $cheque['estado_alerta']);
    }

    /**
     * Test para venta con pagos mixtos (efectivo + cheque).
     */
    public function test_venta_con_pagos_mixtos_calcula_resumen_correctamente()
    {
        // ARRANGE
        $response = $this->postJson('/api/v1/ventas', [
            'cliente_id' => $this->cliente->id,
            'fecha' => now()->format('Y-m-d'),
            'items' => [[
                'producto_id' => $this->producto->id,
                'cantidad' => 5,
                'precio_unitario' => 1000,
            ]], // Total: 5000
            'pagos' => [
                [
                    'metodo_pago_id' => $this->metodoEfectivo->id,
                    'monto' => 2000,
                ],
                [
                    'metodo_pago_id' => $this->metodoCheque->id,
                    'monto' => 3000,
                    'numero_cheque' => '99887766',
                    'fecha_cheque' => now()->format('Y-m-d'),
                    'fecha_cobro' => now()->addDays(30)->format('Y-m-d'),
                ],
            ],
        ]);

        $response->assertStatus(201);
        $venta = Venta::latest()->first();

        // ACT: Obtener resumen
        $resumen = $this->getJson("/api/v1/ventas/{$venta->id}/pagos/resumen");

        // ASSERT BUG 1: Cada método debe contarse correctamente
        $resumen->assertJson([
            'total_venta' => 5000.0,
            'total_cobrado' => 2000.0, // Efectivo
            'total_cheques' => 3000.0, // Cheque
            'total_deuda_cc' => 0.0,
            'saldo_pendiente' => 0.0,
        ]);
    }
}
