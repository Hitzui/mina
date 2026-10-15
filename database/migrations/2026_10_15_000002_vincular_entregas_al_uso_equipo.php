<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipo_insumos', function (Blueprint $table) {
            $table->foreignId('proceso_equipo_id')->nullable()->constrained('proceso_equipos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipo_insumos', fn (Blueprint $table) => $table->dropConstrainedForeignId('proceso_equipo_id'));
    }
};
