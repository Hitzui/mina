<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `movimientos_costos` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('movimientos_costos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('orden_trabajo_id');
            $table->unsignedBigInteger('proceso_orden_id')->nullable();
            $table->unsignedBigInteger('categoria_costo_id');
            $table->date('fecha');
            $table->string('descripcion', 255);
            $table->decimal('cantidad', 12, 3)->default(1.000);
            $table->decimal('costo_unitario', 14, 2)->default(0.00);
            $table->decimal('costo_total', 14, 2)->default(0.00);
            $table->decimal('costo_unitario_nio', 14, 4)->nullable();
            $table->decimal('costo_total_nio', 14, 2)->nullable();
            $table->unsignedBigInteger('moneda_id');
            $table->text('observaciones')->nullable();
            $table->foreign('categoria_costo_id', 'fk_costo_categoria')->references('id')->on('categorias_costos')->onDelete('restrict');
            $table->foreign('moneda_id', 'fk_costo_moneda')->references('id')->on('monedas')->onDelete('restrict');
            $table->foreign('orden_trabajo_id', 'fk_costo_orden')->references('id')->on('ordenes_trabajo')->onDelete('restrict');
            $table->foreign('proceso_orden_id', 'fk_costo_proceso')->references('id')->on('procesos_orden')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_costos');
    }
};
