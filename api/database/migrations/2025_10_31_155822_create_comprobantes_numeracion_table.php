<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comprobantes_numeracion', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_comprobante'); // 'Factura A', 'Factura B', etc.
            $table->string('punto_venta')->default('0001'); // Punto de venta (ej: 0001)
            $table->integer('ultimo_numero')->default(0); // Último número usado
            $table->timestamps();

            $table->unique(['tipo_comprobante', 'punto_venta']);
        });

        // Insertar registros iniciales para cada tipo de comprobante
        DB::table('comprobantes_numeracion')->insert([
            ['tipo_comprobante' => 'Factura A', 'punto_venta' => '0001', 'ultimo_numero' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['tipo_comprobante' => 'Factura B', 'punto_venta' => '0001', 'ultimo_numero' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['tipo_comprobante' => 'Factura C', 'punto_venta' => '0001', 'ultimo_numero' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['tipo_comprobante' => 'Ticket', 'punto_venta' => '0001', 'ultimo_numero' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comprobantes_numeracion');
    }
};
