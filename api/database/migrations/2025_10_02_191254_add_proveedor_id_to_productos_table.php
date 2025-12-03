<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Si ya existe, no lo dupliques
            if (!Schema::hasColumn('productos', 'proveedor_id')) {
                // Laravel 8+:
                $table->foreignId('proveedor_id')
                    ->nullable()
                    ->constrained('proveedores')
                    ->nullOnDelete()
                    ->after('id');
                // Alternativa (si tu versión no soporta constrained/nullOnDelete):
                // $table->unsignedBigInteger('proveedor_id')->nullable()->after('id');
                // $table->foreign('proveedor_id')->references('id')->on('proveedores')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'proveedor_id')) {
                // Si pusiste foreign manual, primero soltá la FK
                // $table->dropForeign(['proveedor_id']);
                $table->dropConstrainedForeignId('proveedor_id'); // Laravel 8+
                // Alternativa:
                // $table->dropColumn('proveedor_id');
            }
        });
    }
};
