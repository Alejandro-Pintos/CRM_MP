<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\MetodoPago;
use App\Models\MovimientoCuentaCorriente;
use App\Models\Cheque;
use App\Services\Ventas\RegistrarVentaService;
use App\Services\Finanzas\CuentaCorrienteService;
use App\Services\Finanzas\ChequeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

class RegistrarVentaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RegistrarVentaService $service;
    protected Cliente $cliente;
    protected Producto $producto;
    protected MetodoPago $metodoPagoEfectivo;
    protected MetodoPago $metodoPagoCheque;
    protected MetodoPago $metodoPagoCC;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario para las ventas
        $this->usuario = \App\Models\Usuario::create([
            'nombre' => 'Usuario',
            'apellido' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        // Autenticar el usuario
        $this->actingAs($this->usuario, 'api');

        // Inicializar servicio con dependencias
        $this->service = new RegistrarVentaService(
            new CuentaCorrienteService(),
            new ChequeService(new CuentaCorrienteService())
        );

        // Crear cliente con límite de crédito
        $this->cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'limite_credito' => 5000000,
            'saldo_actual' => 0,
        ]);

        // Crear producto
        $this->producto = Producto::create([
            'codigo' => 'PROD001',
            'nombre' => 'Producto Test',
            'precio' => 100000,
            'stock' => 100,
        ]);

        // Crear métodos de pago
        $this->metodoPagoEfectivo = MetodoPago::create([
            'nombre' => 'Efectivo',
            'requiere_datos_adicionales' => false,
        ]);

        $this->metodoPagoCheque = MetodoPago::create([
            'nombre' => 'Cheque',
            'requiere_datos_adicionales' => true,
        ]);

        $this->metodoPagoCC = MetodoPago::create([
            'nombre' => 'Cuenta Corriente',
            'requiere_datos_adicionales' => false,
        ]);
    }

    /**
     * Test #1: Puede crear venta con pago completo en efectivo
     */
    public function test_puede_crear_venta_con_pago_completo_efectivo()
    {
        $data = [
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 100000,
                    'iva' => 0,
                ]
            ],
            'pagos' => [
                [
                    'metodo_pago_id' => $this->metodoPagoEfectivo->id,
                    'monto' => 1000000,
                    'fecha_pago' => now(),
                ]
            ],
            'fecha' => now(),
        ];

        $venta = $this->service->ejecutar($this->cliente, $data);

        // Verificaciones
        $this->assertInstanceOf(Venta::class, $venta);
        $this->assertEquals(1000000, $venta->total);
        $this->assertEquals('pagado', $venta->estado_pago);
        $this->assertEquals(1, $venta->items()->count());
        $this->assertEquals(1, $venta->pagos()->count());
        
        // NO debe haber movimientos en CC (pagado 100%)
        $this->assertEquals(0, MovimientoCuentaCorriente::where('cliente_id', $this->cliente->id)->count());
        
        // Saldo del cliente debe seguir en 0
        $this->assertEquals(0, $this->cliente->fresh()->saldo_actual);
    }

    /**
     * Test #2: Puede crear venta con pago parcial y cuenta corriente
     */
    public function test_puede_crear_venta_con_pago_parcial_y_cuenta_corriente()
    {
        $data = [
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 100000,
                    'iva' => 0,
                ]
            ],
            'pagos' => [
                [
                    'metodo_pago_id' => $this->metodoPagoEfectivo->id,
                    'monto' => 400000, // Pago parcial: 40%
                    'fecha_pago' => now(),
                ]
            ],
            'fecha' => now(),
        ];

        $venta = $this->service->ejecutar($this->cliente, $data);

        // Verificaciones
        $this->assertEquals(1000000, $venta->total);
        $this->assertEquals('parcial', $venta->estado_pago);
        
        // Debe haber movimiento en CC por $600,000
        $this->assertEquals(1, MovimientoCuentaCorriente::where('cliente_id', $this->cliente->id)->count());
        
        $movimiento = MovimientoCuentaCorriente::where('cliente_id', $this->cliente->id)->first();
        $this->assertEquals(600000, $movimiento->debe);
        $this->assertEquals(0, $movimiento->haber);
        $this->assertEquals('venta', $movimiento->tipo);
        
        // Saldo del cliente debe ser $600,000
        $this->assertEquals(600000, $this->cliente->fresh()->saldo_actual);
    }

    /**
     * Test #3: Rechaza venta que excede límite de crédito
     */
    public function test_rechaza_venta_que_excede_limite_credito()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('límite de crédito');

        $data = [
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 100, // 100 * 100,000 = 10M (excede límite de 5M)
                    'precio_unitario' => 100000,
                    'iva' => 0,
                ]
            ],
            'pagos' => [], // Sin pagos = todo a crédito
            'fecha' => now(),
        ];

        $this->service->ejecutar($this->cliente, $data);
    }

    /**
     * Test #4: Registra cheque automáticamente si método de pago es cheque
     */
    public function test_registra_cheque_automaticamente_si_metodo_es_cheque()
    {
        $data = [
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 100000,
                    'iva' => 0,
                ]
            ],
            'pagos' => [
                [
                    'metodo_pago_id' => $this->metodoPagoCheque->id,
                    'monto' => 1000000,
                    'fecha_pago' => now(),
                    'numero_cheque' => '12345678',
                    'fecha_cheque' => now(),
                    'fecha_cobro' => now()->addDays(30),
                ]
            ],
            'fecha' => now(),
        ];

        $venta = $this->service->ejecutar($this->cliente, $data);

        // Verificar que se creó el cheque
        $this->assertEquals(1, Cheque::where('venta_id', $venta->id)->count());
        
        $cheque = Cheque::where('venta_id', $venta->id)->first();
        $this->assertEquals('12345678', $cheque->numero);
        $this->assertEquals(1000000, $cheque->monto);
        $this->assertEquals('pendiente', $cheque->estado);
        $this->assertEquals($this->cliente->id, $cheque->cliente_id);
    }

    /**
     * Test #5: Calcula total desde items ignorando total del frontend
     */
    public function test_calcula_total_desde_items_ignorando_total_frontend()
    {
        $data = [
            'total' => 999999999, // Frontend envía total INCORRECTO (malicioso)
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 5,
                    'precio_unitario' => 100000,
                    'iva' => 21, // 21% de IVA
                ]
            ],
            'pagos' => [
                [
                    'metodo_pago_id' => $this->metodoPagoEfectivo->id,
                    'monto' => 605000, // Monto correcto con IVA
                    'fecha_pago' => now(),
                ]
            ],
            'fecha' => now(),
        ];

        $venta = $this->service->ejecutar($this->cliente, $data);

        // Backend calcula: 5 * 100,000 * 1.21 = 605,000
        $this->assertEquals(605000, $venta->total);
        $this->assertNotEquals(999999999, $venta->total); // Ignoró el total del frontend
    }

    /**
     * Test #6: Actualiza saldo del cliente correctamente
     */
    public function test_actualiza_saldo_cliente_correctamente()
    {
        // Primera venta: $1M, paga $400K
        $data1 = [
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 100000,
                    'iva' => 0,
                ]
            ],
            'pagos' => [
                [
                    'metodo_pago_id' => $this->metodoPagoEfectivo->id,
                    'monto' => 400000,
                    'fecha_pago' => now(),
                ]
            ],
            'fecha' => now(),
        ];

        $this->service->ejecutar($this->cliente, $data1);
        $this->assertEquals(600000, $this->cliente->fresh()->saldo_actual);

        // Segunda venta: $500K, paga $200K
        $data2 = [
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 5,
                    'precio_unitario' => 100000,
                    'iva' => 0,
                ]
            ],
            'pagos' => [
                [
                    'metodo_pago_id' => $this->metodoPagoEfectivo->id,
                    'monto' => 200000,
                    'fecha_pago' => now(),
                ]
            ],
            'fecha' => now(),
        ];

        $this->service->ejecutar($this->cliente, $data2);
        
        // Saldo total: $600K (venta 1) + $300K (venta 2) = $900K
        $this->assertEquals(900000, $this->cliente->fresh()->saldo_actual);
    }

    /**
     * Test #7: Venta sin pagos queda pendiente
     */
    public function test_venta_sin_pagos_queda_pendiente()
    {
        $data = [
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 100000,
                    'iva' => 0,
                ]
            ],
            'pagos' => [], // Sin pagos
            'fecha' => now(),
        ];

        $venta = $this->service->ejecutar($this->cliente, $data);

        $this->assertEquals('pendiente', $venta->estado_pago);
        $this->assertEquals(1000000, $this->cliente->fresh()->saldo_actual);
    }

    /**
     * Test #8: Rollback si falla registro en cuenta corriente
     */
    public function test_rollback_si_falla_registro_en_cuenta_corriente()
    {
        // Crear cliente SIN límite de crédito y SIN saldo disponible
        $clienteSinCredito = Cliente::create([
            'nombre' => 'Sin',
            'apellido' => 'Crédito',
            'limite_credito' => 0, // Sin crédito
            'saldo_actual' => 0,
        ]);

        $data = [
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 100000,
                    'iva' => 0,
                ]
            ],
            'pagos' => [], // Sin pagos = requiere crédito
            'fecha' => now(),
        ];

        try {
            $this->service->ejecutar($clienteSinCredito, $data);
            $this->fail('Debería haber lanzado ValidationException');
        } catch (ValidationException $e) {
            // Verificar que NO se creó la venta (rollback exitoso)
            $this->assertEquals(0, Venta::where('cliente_id', $clienteSinCredito->id)->count());
            
            // Verificar que NO se crearon items
            $this->assertEquals(0, \App\Models\DetalleVenta::count());
            
            // Verificar que NO se crearon movimientos
            $this->assertEquals(0, MovimientoCuentaCorriente::where('cliente_id', $clienteSinCredito->id)->count());
        }
    }

    /**
     * Test #9: Venta con múltiples items calcula total correcto
     */
    public function test_venta_con_multiples_items_calcula_total_correcto()
    {
        $producto2 = Producto::create([
            'codigo' => 'PROD002',
            'nombre' => 'Producto 2',
            'precio' => 50000,
            'stock' => 100,
        ]);

        $data = [
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 100000,
                    'iva' => 0,
                ],
                [
                    'producto_id' => $producto2->id,
                    'cantidad' => 5,
                    'precio_unitario' => 50000,
                    'iva' => 21, // Con IVA
                ]
            ],
            'pagos' => [
                [
                    'metodo_pago_id' => $this->metodoPagoEfectivo->id,
                    'monto' => 1302500,
                    'fecha_pago' => now(),
                ]
            ],
            'fecha' => now(),
        ];

        $venta = $this->service->ejecutar($this->cliente, $data);

        // Total: (10 * 100,000) + (5 * 50,000 * 1.21) = 1,000,000 + 302,500 = 1,302,500
        $this->assertEquals(1302500, $venta->total);
        $this->assertEquals(2, $venta->items()->count());
    }

    /**
     * Test #10: Venta con múltiples métodos de pago
     */
    public function test_venta_con_multiples_metodos_de_pago()
    {
        $metodoPagoTransferencia = MetodoPago::create([
            'nombre' => 'Transferencia',
            'requiere_datos_adicionales' => false,
        ]);

        $data = [
            'items' => [
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 100000,
                    'iva' => 0,
                ]
            ],
            'pagos' => [
                [
                    'metodo_pago_id' => $this->metodoPagoEfectivo->id,
                    'monto' => 400000,
                    'fecha_pago' => now(),
                ],
                [
                    'metodo_pago_id' => $metodoPagoTransferencia->id,
                    'monto' => 300000,
                    'fecha_pago' => now(),
                ],
            ],
            'fecha' => now(),
        ];

        $venta = $this->service->ejecutar($this->cliente, $data);

        $this->assertEquals(1000000, $venta->total);
        $this->assertEquals(2, $venta->pagos()->count());
        $this->assertEquals('parcial', $venta->estado_pago); // Pagó $700K de $1M
        $this->assertEquals(300000, $this->cliente->fresh()->saldo_actual); // Debe $300K
    }
}
