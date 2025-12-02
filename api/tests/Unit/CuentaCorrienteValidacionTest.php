<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\MovimientoCuentaCorriente;
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
}
