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
        // CORRECCIÓN CRÍTICA: La tabla compras debe referenciar a proveedores, no clientes
        // Eliminar FK incorrecta y crear la correcta
        Schema::table('compras', function (Blueprint $table) {
            // Eliminar foreign key incorrecta
            $table->dropForeign(['cliente_id']);
            
            // Renombrar columna
            $table->renameColumn('cliente_id', 'proveedor_id');
        });
        
        Schema::table('compras', function (Blueprint $table) {
            // Agregar foreign key correcta
            $table->foreign('proveedor_id')
                ->references('id')
                ->on('proveedores')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropForeign(['proveedor_id']);
            $table->renameColumn('proveedor_id', 'cliente_id');
        });
        
        Schema::table('compras', function (Blueprint $table) {
            $table->foreign('cliente_id')
                ->references('id')
                ->on('clientes')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }
};
