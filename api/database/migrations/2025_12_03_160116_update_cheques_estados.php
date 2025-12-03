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
        DB::statement("ALTER TABLE cheques MODIFY COLUMN estado ENUM('pendiente', 'cobrado', 'debitado', 'rechazado', 'anulado') NOT NULL DEFAULT 'pendiente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE cheques MODIFY COLUMN estado ENUM('pendiente', 'cobrado', 'rechazado') NOT NULL DEFAULT 'pendiente'");
    }
};
