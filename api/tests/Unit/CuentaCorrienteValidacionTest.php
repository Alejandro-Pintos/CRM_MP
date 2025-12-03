<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\Usuario;
use App\Models\MovimientoCuentaCorriente;
use App\Services\Finanzas\CuentaCorrienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CuentaCorrienteValidacionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test #1: Verificar que calcularSaldoReal() usa campos debe/haber
     */
    public function test_calcular_saldo_real_usa_debe_haber()
    {
        // Crear cliente
        $cliente = Cliente::create([
            'nombre' => 'Test',
            'apellido' => 'Cliente',
            'limite_credito' => 5000000,
            'saldo_actual' => 0,
        ]);

        // Crear movimiento: venta $2M
        MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'tipo' => 'venta',
            'venta_id' => null,
            'debe' => 2000000,
            'haber' => 0,
            'monto' => 2000000,
            'fecha' => now(),
            'descripcion' => 'Venta a crédito',
        ]);

        // Verificar saldo calculado
        $this->assertEquals(2000000, $cliente->calcularSaldoReal());

        // Crear movimiento: pago $800K
        MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'tipo' => 'pago',
            'venta_id' => null,
            'debe' => 0,
            'haber' => 800000,
            'monto' => -800000,
            'fecha' => now(),
            'descripcion' => 'Pago parcial',
        ]);

        // Verificar saldo actualizado
        $this->assertEquals(1200000, $cliente->calcularSaldoReal());
    }

    /**
     * Test #2: Verificar consistencia debe - haber
     */
    public function test_consistencia_debe_haber()
    {
        $cliente = Cliente::create([
            'nombre' => 'Test',
            'apellido' => 'Consistencia',
            'limite_credito' => 5000000,
            'saldo_actual' => 0,
        ]);

        // Venta $3M
        MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'tipo' => 'venta',
            'debe' => 3000000,
            'haber' => 0,
            'monto' => 3000000,
            'fecha' => now(),
        ]);

        // Pago $1M
        MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'tipo' => 'pago',
            'debe' => 0,
            'haber' => 1000000,
            'monto' => -1000000,
            'fecha' => now(),
        ]);

        // Pago $2M
        MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'tipo' => 'pago',
            'debe' => 0,
            'haber' => 2000000,
            'monto' => -2000000,
            'fecha' => now(),
        ]);

        // Saldo debe ser 0
        $this->assertEquals(0, $cliente->calcularSaldoReal());
    }

    /**
     * Test #3: Verificar que crédito disponible nunca sea negativo
     */
    public function test_credito_disponible_calculo()
    {
        $cliente = Cliente::create([
            'nombre' => 'Test',
            'apellido' => 'Disponible',
            'limite_credito' => 5000000,
            'saldo_actual' => 0,
        ]);

        // Venta hasta el límite
        MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'tipo' => 'venta',
            'debe' => 5000000,
            'haber' => 0,
            'monto' => 5000000,
            'fecha' => now(),
        ]);

        $saldoReal = $cliente->calcularSaldoReal();
        $creditoDisponible = $cliente->limite_credito - $saldoReal;

        // Verificar que saldo = límite y disponible = 0
        $this->assertEquals(5000000, $saldoReal);
        $this->assertEquals(0, $creditoDisponible);
        $this->assertGreaterThanOrEqual(0, $creditoDisponible);
    }

    /**
     * Test #4: Verificar múltiples movimientos
     */
    public function test_multiples_movimientos()
    {
        $cliente = Cliente::create([
            'nombre' => 'Test',
            'apellido' => 'Multiple',
            'limite_credito' => 10000000,
            'saldo_actual' => 0,
        ]);

        // Secuencia de movimientos
        $movimientos = [
            ['tipo' => 'venta', 'debe' => 1000000, 'haber' => 0],
            ['tipo' => 'venta', 'debe' => 2000000, 'haber' => 0],
            ['tipo' => 'pago', 'debe' => 0, 'haber' => 500000],
            ['tipo' => 'venta', 'debe' => 1500000, 'haber' => 0],
            ['tipo' => 'pago', 'debe' => 0, 'haber' => 1000000],
        ];

        foreach ($movimientos as $mov) {
            MovimientoCuentaCorriente::create([
                'cliente_id' => $cliente->id,
                'tipo' => $mov['tipo'],
                'debe' => $mov['debe'],
                'haber' => $mov['haber'],
                'monto' => $mov['debe'] > 0 ? $mov['debe'] : -$mov['haber'],
                'fecha' => now(),
            ]);
        }

        // Saldo = (1M + 2M + 1.5M) - (0.5M + 1M) = 4.5M - 1.5M = 3M
        $this->assertEquals(3000000, $cliente->calcularSaldoReal());
    }

    /**
     * Test #5: Verificar que saldos negativos sean detectados y rechazados
     */
    public function test_saldo_negativo_lanza_excepcion()
    {
        $cliente = Cliente::create([
            'nombre' => 'Test',
            'apellido' => 'SaldoNegativo',
            'limite_credito' => 5000000,
            'saldo_actual' => 0,
        ]);

        // Crear movimiento: venta $1M
        MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'tipo' => 'venta',
            'debe' => 1000000,
            'haber' => 0,
            'monto' => 1000000,
            'fecha' => now(),
        ]);

        // Crear movimiento: pago $2M (MÁS de lo que debe)
        // Esto simula datos corruptos/pagos huérfanos
        MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'tipo' => 'pago',
            'debe' => 0,
            'haber' => 2000000,
            'monto' => -2000000,
            'fecha' => now(),
        ]);

        // Verificar que calcularSaldoReal() detecta el saldo negativo
        $saldo = $cliente->calcularSaldoReal();
        $this->assertEquals(-1000000, $saldo);

        // Verificar que recalcularSaldo() lanza excepción con saldo negativo
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DATOS CORRUPTOS');

        $cliente->recalcularSaldo();
    }

    /**
     * Test #6: cancelarDeudaPorVenta crea movimiento de reversión
     */
    public function test_cancelar_deuda_por_venta()
    {
        $service = app(CuentaCorrienteService::class);

        $usuario = Usuario::create([
            'nombre' => 'Admin',
            'apellido' => 'Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Cliente',
            'apellido' => 'Test',
            'limite_credito' => 5000000,
            'saldo_actual' => 0,
        ]);

        $venta = Venta::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'total' => 1000000,
            'estado' => 'pagada',
            'fecha' => now(),
        ]);

        // Crear movimiento de deuda
        MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'venta_id' => $venta->id,
            'tipo' => 'venta',
            'debe' => 1000000,
            'haber' => 0,
            'monto' => 1000000,
            'fecha' => now(),
            'descripcion' => 'Venta a crédito',
        ]);
        
        $cliente->update(['saldo_actual' => 1000000]);
        $cliente->refresh();
        $this->assertEquals(1000000, $cliente->saldo_actual);

        // Cancelar deuda
        $service->cancelarDeudaPorVenta($venta);

        // Verificar que existe movimiento de reversión (HABER) con tipo 'cancelacion'
        $movimientos = MovimientoCuentaCorriente::where('venta_id', $venta->id)->get();
        $this->assertCount(2, $movimientos); // Debe + Haber (reversión)
        
        $reversion = $movimientos->where('haber', '>', 0)->first();
        $this->assertNotNull($reversion);
        $this->assertEquals('cancelacion', $reversion->tipo);
        $this->assertEquals(1000000, $reversion->haber);

        // ✅ CORREGIDO: El saldo ahora SÍ se recalcula correctamente
        $cliente->refresh();
        $this->assertEquals(0, $cliente->saldo_actual);
    }

    /**
     * Test #7: registrarPagoPorCheque reduce deuda correctamente
     */
    public function test_registrar_pago_por_cheque()
    {
        $service = app(CuentaCorrienteService::class);

        $usuario = Usuario::create([
            'nombre' => 'Admin',
            'apellido' => 'Test',
            'email' => 'admin2@test.com',
            'password' => bcrypt('password'),
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Cliente',
            'apellido' => 'Cheque',
            'limite_credito' => 5000000,
            'saldo_actual' => 2000000, // Deuda inicial
        ]);

        $venta = Venta::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'total' => 2000000,
            'estado' => 'pagada',
            'fecha' => now(),
        ]);

        // Crear deuda inicial
        MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'venta_id' => $venta->id,
            'tipo' => 'venta',
            'debe' => 2000000,
            'haber' => 0,
            'monto' => 2000000,
            'fecha' => now(),
            'descripcion' => 'Venta inicial',
        ]);

        // Registrar pago por cheque de $800K
        $movimiento = $service->registrarPagoPorCheque(
            clienteId: $cliente->id,
            ventaId: $venta->id,
            monto: 800000,
            fecha: now(),
            observaciones: 'Cheque #12345'
        );

        $this->assertNotNull($movimiento);
        $this->assertEquals(800000, $movimiento->haber);
        $this->assertEquals(0, $movimiento->debe);
        $this->assertEquals('pago', $movimiento->tipo);

        // Verificar saldo actualizado
        $cliente->refresh();
        $this->assertEquals(1200000, $cliente->saldo_actual); // 2M - 800K
    }

    /**
     * Test #8: calcularDeudaCCVenta retorna deuda pendiente correcta
     */
    public function test_calcular_deuda_cc_venta()
    {
        $service = app(CuentaCorrienteService::class);

        $usuario = Usuario::create([
            'nombre' => 'Admin',
            'apellido' => 'Test',
            'email' => 'admin3@test.com',
            'password' => bcrypt('password'),
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Cliente',
            'apellido' => 'Deuda',
            'limite_credito' => 5000000,
            'saldo_actual' => 0,
        ]);

        $venta = Venta::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'total' => 1500000,
            'estado' => 'pagada',
            'fecha' => now(),
        ]);

        // Crear movimiento deuda $1.5M
        MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'venta_id' => $venta->id,
            'tipo' => 'venta',
            'debe' => 1500000,
            'haber' => 0,
            'monto' => 1500000,
            'fecha' => now(),
            'descripcion' => 'Venta a crédito',
        ]);

        // Crear pago parcial $600K
        MovimientoCuentaCorriente::create([
            'cliente_id' => $cliente->id,
            'venta_id' => $venta->id,
            'tipo' => 'pago',
            'debe' => 0,
            'haber' => 600000,
            'monto' => -600000,
            'fecha' => now(),
            'descripcion' => 'Pago parcial',
        ]);

        // Verificar deuda pendiente = $900K
        $deudaPendiente = $service->calcularDeudaCCVenta($venta->id);
        $this->assertEquals(900000, $deudaPendiente);
    }
}
