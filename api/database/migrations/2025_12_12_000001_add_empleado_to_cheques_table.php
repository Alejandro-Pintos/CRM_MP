<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            // Agregar relación con empleados para cheques de nómina
            $table->foreignId('empleado_id')
                  ->nullable()
                  ->after('proveedor_id')
                  ->constrained('empleados')
                  ->onDelete('cascade');
            
            $table->foreignId('pago_empleado_id')
                  ->nullable()
                  ->after('pago_proveedor_id')
                  ->constrained('pagos_empleados')
                  ->onDelete('set null');
            
            // Índice para mejorar rendimiento
            $table->index(['empleado_id', 'estado'], 'cheques_empleado_estado_index');
        });
    }

    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->dropIndex('cheques_empleado_estado_index');
            $table->dropForeign(['empleado_id']);
            $table->dropForeign(['pago_empleado_id']);
            $table->dropColumn(['empleado_id', 'pago_empleado_id']);
        });
    }
};
