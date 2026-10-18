<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipo_reparaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_equipo_id')->constrained('proceso_equipos');
            $table->dateTime('inicio');
            $table->dateTime('fin')->nullable();
            $table->string('motivo', 1000);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['proceso_equipo_id', 'inicio']);
        });
        Schema::table('movimientos_costos', function (Blueprint $table) {
            $table->foreignId('reparacion_id')->nullable()->constrained('equipo_reparaciones');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_costos', fn (Blueprint $table) => $table->dropConstrainedForeignId('reparacion_id'));
        Schema::dropIfExists('equipo_reparaciones');
    }
};
