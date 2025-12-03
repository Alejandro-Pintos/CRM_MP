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
        Schema::table('movimientos_cuenta_corriente', function (Blueprint $table) {
            // Agregar venta_id explícito para vincular pagos a ventas
            $table->unsignedBigInteger('venta_id')->nullable()->after('referencia_id');
            $table->foreign('venta_id')->references('id')->on('ventas')->onDelete('set null');
            
            // Índice para consultas por venta
            $table->index('venta_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_cuenta_corriente', function (Blueprint $table) {
            $table->dropForeign(['venta_id']);
            $table->dropIndex(['venta_id']);
            $table->dropColumn('venta_id');
        });
    }
};
