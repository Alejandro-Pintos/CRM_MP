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
        Schema::table('movimientos_cuenta_corriente', function (Blueprint $table) {
            $table->decimal('debe', 15, 2)->default(0)->after('monto');
            $table->decimal('haber', 15, 2)->default(0)->after('debe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_cuenta_corriente', function (Blueprint $table) {
            $table->dropColumn(['debe', 'haber']);
        });
    }
};
