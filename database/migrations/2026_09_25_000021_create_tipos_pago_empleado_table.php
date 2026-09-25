<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `tipos_pago_empleado` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('tipos_pago_empleado', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 50);
            $table->string('codigo', 20)->nullable();
            $table->string('metodo_calculo', 30)->default("CANTIDAD_X_TARIFA");
            $table->string('descripcion', 255)->nullable();
            $table->boolean('estado')->default(1);
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_pago_empleado');
    }
};
