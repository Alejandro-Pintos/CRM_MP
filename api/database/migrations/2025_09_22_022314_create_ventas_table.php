<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('usuario_id');
            $table->dateTime('fecha')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('estado_pago')->default('pendiente'); // pendiente|parcial|pagado
            $table->string('tipo_comprobante')->nullable();
            $table->string('numero_comprobante')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['cliente_id','fecha']);
            $table->foreign('cliente_id')->references('id')->on('clientes');
            $table->foreign('usuario_id')->references('id')->on('usuarios');
        });
    }
    public function down(): void { Schema::dropIfExists('ventas'); }
};
