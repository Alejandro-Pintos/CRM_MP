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
        Schema::table('pagos', function (Blueprint $table) {
            // Campo para estado del cheque: null (no es cheque), 'pendiente', 'cobrado', 'rechazado'
            $table->string('estado_cheque')->nullable()->after('fecha_pago');
            
            // Campos adicionales para información del cheque
            $table->string('numero_cheque')->nullable()->after('estado_cheque');
            $table->date('fecha_cheque')->nullable()->after('numero_cheque'); // Fecha del cheque
            $table->date('fecha_cobro')->nullable()->after('fecha_cheque'); // Fecha en que se cobró/rechazó
            $table->text('observaciones_cheque')->nullable()->after('fecha_cobro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn(['estado_cheque', 'numero_cheque', 'fecha_cheque', 'fecha_cobro', 'observaciones_cheque']);
        });
    }
};
