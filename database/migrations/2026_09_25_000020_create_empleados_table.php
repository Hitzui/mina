<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `empleados` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 30);
            $table->string('nombre', 150);
            $table->string('telefono', 30)->nullable();
            $table->unsignedBigInteger('tipo_empleado_id');
            $table->date('fecha_ingreso')->nullable();
            $table->boolean('estado')->default(1);
            $table->text('observaciones')->nullable();
            $table->unique(['codigo'], 'codigo');
            $table->foreign('tipo_empleado_id', 'FK_empleados_tipo_empleado')->references('id')->on('tipos_empleado')->onDelete('no action');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
