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
        Schema::create('pagos_proveedores', function (Blueprint $table) {
            $table->id();
            
            // Relación con proveedor
            $table->foreignId('proveedor_id')
                ->constrained('proveedores')
                ->onDelete('cascade');
            
            // Datos del pago
            $table->date('fecha_pago');
            $table->decimal('monto', 15, 2);
            
            // Método de pago (reutilizando catálogo existente)
            $table->foreignId('metodo_pago_id')
                ->nullable()
                ->constrained('metodos_pago')
                ->onDelete('set null');
            
            // Referencia y concepto
            $table->string('referencia', 100)->nullable()
                ->comment('Número de factura, orden de compra, etc.');
            
            $table->string('concepto', 150)
                ->comment('Ej: Pago factura X, anticipo, cancelación deuda');
            
            $table->text('observaciones')->nullable();
            
            // Usuario que registró el pago (si existe el modelo Usuario/User)
            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('usuarios')
                ->onDelete('set null');
            
            $table->timestamps();
            
            // Índices para mejorar performance
            $table->index(['proveedor_id', 'fecha_pago']);
            $table->index('metodo_pago_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos_proveedores');
    }
};
