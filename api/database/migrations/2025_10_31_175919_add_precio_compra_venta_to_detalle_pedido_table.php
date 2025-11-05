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
        Schema::table('detalle_pedido', function (Blueprint $table) {
            $table->decimal('precio_compra', 12, 2)->default(0)->after('cantidad');
            $table->decimal('precio_venta', 12, 2)->default(0)->after('precio_compra');
            $table->decimal('porcentaje_iva', 5, 2)->default(21.00)->after('precio_venta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_pedido', function (Blueprint $table) {
            $table->dropColumn(['precio_compra', 'precio_venta', 'porcentaje_iva']);
        });
    }
};
