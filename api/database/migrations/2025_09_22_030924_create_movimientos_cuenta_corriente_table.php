<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('movimientos_cuenta_corriente', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id');
            $table->string('tipo'); // venta|pago|ajuste|nota_credito
            $table->unsignedBigInteger('referencia_id')->nullable(); // id venta/pago/...
            $table->decimal('monto', 15, 2); // signo: venta + ; pago -
            $table->dateTime('fecha')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index(['cliente_id','fecha']);
            $table->foreign('cliente_id')->references('id')->on('clientes');
        });
    }
    public function down(): void { Schema::dropIfExists('movimientos_cuenta_corriente'); }
};
