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
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->dateTime('fecha_pedido');
            $table->dateTime('fecha_entrega_aprox')->nullable();
            $table->string('estado')->default('pendiente'); // pendiente, en_proceso, entregado, cancelado
            $table->string('direccion_entrega')->nullable();
            $table->string('ciudad_entrega')->nullable();
            
            // Datos del clima
            $table->string('clima_estado')->nullable(); // Ej: "Soleado", "Nublado", "Lluvia"
            $table->decimal('clima_temperatura', 5, 2)->nullable(); // Temperatura en °C
            $table->integer('clima_humedad')->nullable(); // Porcentaje
            $table->string('clima_descripcion')->nullable(); // Descripción detallada
            $table->text('clima_json')->nullable(); // JSON completo de la respuesta de la API
            
            $table->text('observaciones')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('cascade');
            $table->foreign('venta_id')->references('id')->on('ventas')->onDelete('set null');
            $table->index(['cliente_id', 'fecha_pedido']);
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
