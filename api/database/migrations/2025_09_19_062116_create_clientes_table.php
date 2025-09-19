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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('email')->nullable()->index();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('provincia')->nullable();
            $table->string('cuit_cuil')->nullable()->unique();
            $table->timestamp('fecha_registro')->useCurrent();
            $table->timestamp('fecha_ultima_compra')->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->decimal('saldo_actual', 12, 2)->default(0);
            $table->decimal('limite_credito', 12, 2)->default(0);
            $table->softDeletes(); // <-- SoftDeletes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
