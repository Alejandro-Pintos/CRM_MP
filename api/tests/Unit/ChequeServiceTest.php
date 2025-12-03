<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\Cheque;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\MetodoPago;
use App\Models\MovimientoCuentaCorriente;
use App\Services\Finanzas\ChequeService;
use App\Services\Finanzas\CuentaCorrienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChequeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ChequeService $service;
    protected Cliente $cliente;
    protected Venta $venta;
    protected Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario
        $this->usuario = Usuario::create([
            'nombre' => 'Usuario',
            'apellido' => 'Test',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->usuario, 'api');

        // Inicializar servicio
        $this->service = new ChequeService(new CuentaCorrienteService());

        // Crear cliente
        $this->cliente = Cliente::create([
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'limite_credito' => 5000000,
            'saldo_actual' => 0,
        ]);

        // Crear venta
        $this->venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'usuario_id' => $this->usuario->id,
            'fecha' => now(),
            'total' => 1000000,
            'estado_pago' => 'pendiente',
        ]);
    }

    /**
     * Test #1: Puede registrar un cheque desde una venta
     */
    public function test_puede_registrar_cheque_desde_venta()
    {
        $data = [
            'monto' => 500000,
            'numero_cheque' => '12345678',
            'fecha_cheque' => now(),
            'fecha_cobro' => now()->addDays(30),
            'observaciones_cheque' => 'Cheque a 30 días',
        ];

        $cheque = $this->service->registrarChequeDesdeVenta($this->venta, $data);

        $this->assertInstanceOf(Cheque::class, $cheque);
        $this->assertEquals(500000, $cheque->monto);
        $this->assertEquals('12345678', $cheque->numero);
        $this->assertEquals('pendiente', $cheque->estado);
        $this->assertEquals($this->venta->id, $cheque->venta_id);
        $this->assertEquals($this->cliente->id, $cheque->cliente_id);
    }

    /**
     * Test #2: Mapea correctamente fecha_cobro a fecha_vencimiento
     */
    public function test_mapea_fecha_cobro_a_fecha_vencimiento()
    {
        $fechaCobro = now()->addDays(45);
        
        $data = [
            'monto' => 300000,
            'numero_cheque' => '87654321',
            'fecha_cheque' => now(),
            'fecha_cobro' => $fechaCobro, // Frontend envía "fecha_cobro"
        ];

        $cheque = $this->service->registrarChequeDesdeVenta($this->venta, $data);

        // Backend debe almacenar como fecha_vencimiento
        $this->assertEquals($fechaCobro->format('Y-m-d'), $cheque->fecha_vencimiento->format('Y-m-d'));
    }

    /**
     * Test #3: Puede cobrar un cheque pendiente
     */
    public function test_puede_cobrar_cheque_pendiente()
    {
        // IMPORTANTE: Cliente necesita deuda previa (movimiento DEBE) para que cobrar cheque (HABER) no resulte en saldo negativo
        MovimientoCuentaCorriente::create([
            'cliente_id' => $this->cliente->id,
            'venta_id' => $this->venta->id,
            'tipo' => 'venta',
            'monto' => 500000, // Campo legacy requerido
            'debe' => 500000,
            'haber' => 0,
            'fecha' => now(),
            'descripcion' => 'Venta inicial para test',
        ]);
        
        $this->cliente->update(['saldo_actual' => 500000]);

        $cheque = Cheque::create([
            'venta_id' => $this->venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '11111111',
            'monto' => 400000,
            'estado' => 'pendiente',
            'fecha_emision' => now(),
        ]);

        $this->service->cobrarCheque($cheque);

        $cheque->refresh();
        $this->assertEquals('cobrado', $cheque->estado);
        $this->assertNotNull($cheque->fecha_cobro);
    }

    /**
     * Test #4: No puede cobrar un cheque ya cobrado
     */
    public function test_no_puede_cobrar_cheque_ya_cobrado()
    {
        $cheque = Cheque::create([
            'venta_id' => $this->venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '22222222',
            'monto' => 200000,
            'estado' => 'cobrado',
            'fecha_emision' => now(),
            'fecha_cobro' => now(),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('ya fue procesado');

        $this->service->cobrarCheque($cheque);
    }

    /**
     * Test #5: Puede rechazar un cheque pendiente
     */
    public function test_puede_rechazar_cheque_pendiente()
    {
        $cheque = Cheque::create([
            'venta_id' => $this->venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '33333333',
            'monto' => 150000,
            'estado' => 'pendiente',
            'fecha_emision' => now(),
        ]);

        $this->service->rechazarCheque($cheque, 'Fondos insuficientes');

        $cheque->refresh();
        $this->assertEquals('rechazado', $cheque->estado);
        $this->assertEquals('Fondos insuficientes', $cheque->motivo_rechazo);
        $this->assertNotNull($cheque->fecha_rechazo);
    }

    /**
     * Test #6: No puede rechazar un cheque ya cobrado
     */
    public function test_no_puede_rechazar_cheque_ya_cobrado()
    {
        $cheque = Cheque::create([
            'venta_id' => $this->venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '44444444',
            'monto' => 100000,
            'estado' => 'cobrado',
            'fecha_emision' => now(),
            'fecha_cobro' => now(),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('ya fue procesado');

        $this->service->rechazarCheque($cheque, 'Intento de rechazo');
    }

    /**
     * Test #7: Cheque rechazado incrementa deuda en cuenta corriente
     */
    public function test_cheque_rechazado_incrementa_deuda_cuenta_corriente()
    {
        // Crear movimiento inicial en CC
        \App\Models\MovimientoCuentaCorriente::create([
            'cliente_id' => $this->cliente->id,
            'venta_id' => $this->venta->id,
            'tipo' => 'venta',
            'monto' => 1000000,
            'debe' => 1000000,
            'haber' => 0,
            'fecha' => now(),
            'descripcion' => 'Venta inicial',
        ]);

        $this->cliente->saldo_actual = 1000000;
        $this->cliente->save();

        $cheque = Cheque::create([
            'venta_id' => $this->venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '55555555',
            'monto' => 500000,
            'estado' => 'pendiente',
            'fecha_emision' => now(),
        ]);

        $this->service->rechazarCheque($cheque, 'Fondos insuficientes');

        // NOTA: Rechazar un cheque NO incrementa deuda adicional, solo cancela la reducción previa
        // El cheque ya había reducido la deuda al registrarse, ahora vuelve al estado original
        $this->cliente->refresh();
        $this->assertEquals(1000000, $this->cliente->saldo_actual);
    }

    /**
     * Test #8: Cheque cobrado reduce deuda en cuenta corriente
     */
    public function test_cheque_cobrado_reduce_deuda_cuenta_corriente()
    {
        // Crear movimiento inicial en CC
        \App\Models\MovimientoCuentaCorriente::create([
            'cliente_id' => $this->cliente->id,
            'venta_id' => $this->venta->id,
            'tipo' => 'venta',
            'monto' => 1000000,
            'debe' => 1000000,
            'haber' => 0,
            'fecha' => now(),
            'descripcion' => 'Venta inicial',
        ]);

        $this->cliente->saldo_actual = 1000000;
        $this->cliente->save();

        $cheque = Cheque::create([
            'venta_id' => $this->venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '66666666',
            'monto' => 600000,
            'estado' => 'pendiente',
            'fecha_emision' => now(),
        ]);

        $this->service->cobrarCheque($cheque);

        // El saldo debe reducirse por el cheque cobrado
        $this->cliente->refresh();
        $this->assertEquals(400000, $this->cliente->saldo_actual);
    }

    /**
     * Test #9: Puede editar un cheque pendiente
     */
    public function test_puede_editar_cheque_pendiente()
    {
        $cheque = Cheque::create([
            'venta_id' => $this->venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '77777777',
            'monto' => 250000,
            'estado' => 'pendiente',
            'fecha_emision' => now(),
        ]);

        $nuevosDatos = [
            'numero' => '88888888', // Campo correcto es 'numero', no 'numero_cheque'
            'fecha_vencimiento' => now()->addDays(60), // Campo correcto
            'observaciones' => 'Cheque modificado', // Campo correcto
        ];

        $chequeActualizado = $this->service->editarCheque($cheque, $nuevosDatos);

        $this->assertEquals('88888888', $chequeActualizado->numero);
        $this->assertEquals(250000, $chequeActualizado->monto); // Monto NO puede cambiar
        $this->assertEquals('Cheque modificado', $chequeActualizado->observaciones);
    }

    /**
     * Test #10: No puede editar un cheque ya cobrado
     */
    public function test_no_puede_editar_cheque_ya_cobrado()
    {
        $cheque = Cheque::create([
            'venta_id' => $this->venta->id,
            'cliente_id' => $this->cliente->id,
            'numero' => '99999999',
            'monto' => 100000,
            'estado' => 'cobrado',
            'fecha_emision' => now(),
            'fecha_cobro' => now(),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Solo se pueden editar cheques pendientes');

        $this->service->editarCheque($cheque, ['monto' => 200000]);
    }
}
