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
        Schema::create('compra_detalles', function (Blueprint $table) {
           $table->id();
           $table->foreignId('compra_id')->constrained('compras')->onUpdate('cascade')->onDelete('cascade');
           $table->foreignId('producto_id')->constrained('productos')->onUpdate('cascade')->onDelete('restrict');
           $table->string('descripcion'); // Copia del nombre del producto
           $table->string('unidad_medida')->default('u');
           $table->decimal('cantidad', 12, 2);
           $table->decimal('precio_unitario', 12, 2);
           $table->decimal('descuento_item', 12, 2)->default(0);
           $table->decimal('impuesto_porcentaje', 5, 2)->default(0);
           $table->decimal('impuesto_monto', 12, 2)->default(0);
           $table->decimal('subtotal', 12, 2); // cantidad * precio_unitario - descuento_item + impuesto_monto
           $table->softDeletes();
           $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compra_detalles');
    }
};
