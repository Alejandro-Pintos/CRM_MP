<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_completo', 255);
            $table->string('documento', 50)->unique()->comment('DNI o CUIT/CUIL');
            $table->string('telefono', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('puesto', 100)->comment('Ej: chofer, administrativo, vendedor, cargador, tractorista');
            $table->text('notas')->nullable()->comment('Observaciones internas');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('activo');
            $table->index('documento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
