<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `trabajos_empleados` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('trabajos_empleados', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('orden_trabajo_id')->nullable();
            $table->unsignedBigInteger('proceso_orden_id')->nullable();
            $table->unsignedBigInteger('tipo_pago_id');
            $table->date('fecha');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->decimal('cantidad', 10, 2)->default(0.00);
            $table->decimal('tarifa', 14, 2)->default(0.00);
            $table->decimal('total', 14, 2)->default(0.00);
            $table->decimal('tarifa_nio', 14, 4)->nullable();
            $table->decimal('total_nio', 14, 2)->nullable();
            $table->unsignedBigInteger('moneda_id');
            $table->decimal('tipo_cambio', 14, 4)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('unidad', 20)->default("hora");
            $table->foreign('empleado_id', 'fk_trabajo_empleado')->references('id')->on('empleados')->onDelete('restrict');
            $table->foreign('moneda_id', 'fk_trabajo_moneda')->references('id')->on('monedas')->onDelete('restrict');
            $table->foreign('orden_trabajo_id', 'fk_trabajo_orden')->references('id')->on('ordenes_trabajo')->onDelete('set null');
            $table->foreign('proceso_orden_id', 'fk_trabajo_proceso')->references('id')->on('procesos_orden')->onDelete('set null');
            $table->foreign('tipo_pago_id', 'fk_trabajo_tipo_pago')->references('id')->on('tipos_pago_empleado')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajos_empleados');
    }
};
