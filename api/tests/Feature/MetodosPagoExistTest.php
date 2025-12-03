<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\MetodoPago;

/**
 * Test para verificar que métodos de pago básicos existan.
 * 
 * Previene regresiones donde la tabla métodos_pago está vacía.
 */
class MetodosPagoExistTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que verifica la existencia de métodos de pago básicos
     * 
     * @test
     */
    public function basic_payment_methods_must_exist()
    {
        // Ejecutar seeder
        $this->artisan('db:seed', ['--class' => 'MetodoPagoSeeder']);

        $requiredMethods = [
            'Efectivo',
            'Cheque',
            'Cuenta Corriente',
            'Transferencia Bancaria',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                MetodoPago::where('nombre', $method)
                    ->where('estado', 'activo')
                    ->exists(),
                "El método de pago '{$method}' no existe activo en la base de datos"
            );
        }

        // Verificar que hay al menos 4 métodos activos
        $activeMethodsCount = MetodoPago::where('estado', 'activo')->count();
        $this->assertGreaterThanOrEqual(
            4,
            $activeMethodsCount,
            "Deberían existir al menos 4 métodos de pago activos"
        );
    }

    /**
     * Test que verifica que el método "Cuenta Corriente" existe
     * Este es crítico para el sistema de crédito
     * 
     * @test
     */
    public function cuenta_corriente_payment_method_must_exist()
    {
        $this->artisan('db:seed', ['--class' => 'MetodoPagoSeeder']);

        $cuentaCorriente = MetodoPago::where('nombre', 'Cuenta Corriente')
            ->where('estado', 'activo')
            ->first();

        $this->assertNotNull(
            $cuentaCorriente,
            "El método de pago 'Cuenta Corriente' es crítico y debe existir"
        );

        $this->assertEquals('activo', $cuentaCorriente->estado);
    }

    /**
     * Test que verifica que el método "Cheque" existe
     * Este es crítico para el sistema de cheques
     * 
     * @test
     */
    public function cheque_payment_method_must_exist()
    {
        $this->artisan('db:seed', ['--class' => 'MetodoPagoSeeder']);

        $cheque = MetodoPago::where('nombre', 'Cheque')
            ->where('estado', 'activo')
            ->first();

        $this->assertNotNull(
            $cheque,
            "El método de pago 'Cheque' es crítico y debe existir"
        );

        $this->assertEquals('activo', $cheque->estado);
    }
}
