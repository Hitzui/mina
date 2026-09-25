<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `empleados_pagos` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('empleados_pagos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('tipo_pago_id');
            $table->decimal('tarifa', 14, 2)->default(0.00);
            $table->unsignedBigInteger('moneda_id');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->boolean('estado')->default(1);
            $table->text('observaciones')->nullable();
            $table->foreign('empleado_id', 'fk_empleado_pago_empleado')->references('id')->on('empleados')->onDelete('restrict');
            $table->foreign('moneda_id', 'fk_empleado_pago_moneda')->references('id')->on('monedas')->onDelete('restrict');
            $table->foreign('tipo_pago_id', 'fk_empleado_pago_tipo')->references('id')->on('tipos_pago_empleado')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados_pagos');
    }
};
