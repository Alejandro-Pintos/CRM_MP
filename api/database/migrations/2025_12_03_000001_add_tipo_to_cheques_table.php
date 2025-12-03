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
        Schema::table('cheques', function (Blueprint $table) {
            // Agregar tipo de cheque
            $table->enum('tipo', ['recibido', 'emitido'])
                  ->default('recibido')
                  ->after('id')
                  ->comment('recibido = de cliente | emitido = a proveedor');
            
            // Hacer nullable las relaciones de ventas (solo para cheques recibidos)
            $table->foreignId('venta_id')->nullable()->change();
            $table->foreignId('cliente_id')->nullable()->change();
            
            // Agregar relaciones para cheques emitidos
            $table->foreignId('proveedor_id')
                  ->nullable()
                  ->after('cliente_id')
                  ->constrained('proveedores')
                  ->onDelete('cascade');
            
            $table->foreignId('pago_proveedor_id')
                  ->nullable()
                  ->after('pago_id')
                  ->constrained('pagos_proveedores')
                  ->onDelete('set null');
            
            // Agregar banco emisor para cheques emitidos
            $table->string('banco', 100)
                  ->nullable()
                  ->after('numero')
                  ->comment('Banco emisor del cheque');
            
            // Índices para mejorar rendimiento
            $table->index(['tipo', 'estado'], 'cheques_tipo_estado_index');
            $table->index(['proveedor_id', 'estado'], 'cheques_proveedor_estado_index');
        });

        // Actualizar todos los cheques existentes como 'recibidos'
        DB::table('cheques')->update(['tipo' => 'recibido']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            // Eliminar índices
            $table->dropIndex('cheques_tipo_estado_index');
            $table->dropIndex('cheques_proveedor_estado_index');
            
            // Eliminar foreign keys
            $table->dropForeign(['proveedor_id']);
            $table->dropForeign(['pago_proveedor_id']);
            
            // Eliminar columnas
            $table->dropColumn(['tipo', 'proveedor_id', 'pago_proveedor_id', 'banco']);
            
            // Restaurar NOT NULL en venta_id y cliente_id
            // Nota: Solo si no hay datos que lo impidan
            // $table->foreignId('venta_id')->nullable(false)->change();
            // $table->foreignId('cliente_id')->nullable(false)->change();
        });
    }
};
