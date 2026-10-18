<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_reparaciones', function (Blueprint $table) {
            $table->string('modalidad', 20)->nullable();
            $table->text('trabajo_realizado')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('equipo_reparaciones', fn (Blueprint $table) => $table->dropColumn(['modalidad', 'trabajo_realizado']));
    }
};
