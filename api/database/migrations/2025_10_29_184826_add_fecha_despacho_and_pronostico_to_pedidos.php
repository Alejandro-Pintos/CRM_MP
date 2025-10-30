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
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dateTime('fecha_despacho')->nullable()->after('fecha_entrega_aprox');
            $table->text('pronostico_extendido')->nullable()->after('clima_json'); // JSON con pronóstico de 5-7 días
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['fecha_despacho', 'pronostico_extendido']);
        });
    }
};
