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
        Schema::create('pagos_empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')
                ->constrained('empleados')
                ->onDelete('cascade');
            $table->date('fecha_pago');
            $table->decimal('monto', 12, 2);
            $table->foreignId('metodo_pago_id')
                ->nullable()
                ->constrained('metodos_pago')
                ->onDelete('set null');
            $table->string('concepto', 100)->comment('Ej: sueldo, anticipo, extra, bono');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('empleado_id');
            $table->index('fecha_pago');
            $table->index('metodo_pago_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos_empleados');
    }
};
