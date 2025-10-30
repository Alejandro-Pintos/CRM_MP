<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // Código interno o SKU
            $table->string('nombre'); // Ej: Poste de quebracho, tabla de pino
            $table->string('descripcion')->nullable();
            $table->string('unidad_medida')->default('u'); // Ej: unidad, metro, kg
            $table->decimal('precio', 12, 2); // Precio unitario sin IVA
            $table->decimal('iva', 5, 2)->default(21.00); // Ej: 21.00
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->softDeletes(); // SoftDeletes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
