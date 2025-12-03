<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('detalle_venta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('producto_id');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 15, 2);
            $table->decimal('iva', 5, 2)->default(0); // porcentaje
            $table->timestamps();

            $table->index('venta_id');
            $table->index('producto_id');
            $table->foreign('venta_id')->references('id')->on('ventas')->onDelete('cascade');
            $table->foreign('producto_id')->references('id')->on('productos');
        });
    }
    public function down(): void { Schema::dropIfExists('detalle_venta'); }
};
