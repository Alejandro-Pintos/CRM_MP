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
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->onDelete('set null');
            
            // Datos del cheque
            $table->string('numero')->nullable()->comment('Número de cheque');
            $table->decimal('monto', 15, 2);
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            
            // Estado y seguimiento
            $table->enum('estado', ['pendiente', 'cobrado', 'rechazado'])->default('pendiente');
            $table->date('fecha_cobro')->nullable();
            $table->date('fecha_rechazo')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->text('observaciones')->nullable();
            
            $table->timestamps();
            
            // Índices para mejorar rendimiento
            // NOTA: venta_id ya tiene índice por foreignId()->constrained()
            $table->index(['cliente_id', 'estado'], 'cheques_cliente_estado_index');
            $table->index(['fecha_vencimiento'], 'cheques_fecha_venc_index');
            $table->index(['estado', 'fecha_vencimiento'], 'cheques_estado_fecha_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
