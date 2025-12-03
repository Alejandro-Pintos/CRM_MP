<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('metodo_pago_id');
            $table->decimal('monto', 15, 2);
            $table->dateTime('fecha_pago')->nullable();
            $table->timestamps();

            // NOTA: Laravel 12 crea índices automáticamente en foreign keys
            $table->foreign('venta_id')->references('id')->on('ventas')->onDelete('cascade');
            $table->foreign('metodo_pago_id')->references('id')->on('metodos_pago');
        });
    }
    public function down(): void { Schema::dropIfExists('pagos'); }
};
