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
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onUpdate('cascade')->onDelete('restrict');
            $table->timestamp('fecha_compra')->useCurrent();
            $table->enum('estado', ['pendiente', 'pagado', 'anulado'])->default('pendiente');
            $table->enum('metodo_pago', ['efectivo', 'tarjeta', 'transferencia', 'otro'])->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento_global', 12, 2)->default(0);
            $table->decimal('impuestos_total', 12, 2)->default(0);
            $table->decimal('monto_total', 12, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
