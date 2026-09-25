<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `movimientos_inventario` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('orden_trabajo_id')->nullable();
            $table->unsignedBigInteger('proceso_orden_id')->nullable();
            $table->string('tipo', 30);
            $table->dateTime('fecha');
            $table->decimal('cantidad', 14, 3);
            $table->unsignedBigInteger('moneda_id');
            $table->decimal('costo_unitario', 14, 2)->nullable();
            $table->decimal('costo_total', 14, 2)->nullable();
            $table->string('referencia', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->decimal('costo_unitario_nio', 14, 4)->nullable();
            $table->decimal('costo_total_nio', 14, 2)->nullable();
            $table->foreign('moneda_id', 'fk_movimiento_moneda')->references('id')->on('monedas')->onDelete('restrict');
            $table->foreign('orden_trabajo_id', 'fk_movimiento_orden')->references('id')->on('ordenes_trabajo')->onDelete('set null');
            $table->foreign('proceso_orden_id', 'fk_movimiento_proceso')->references('id')->on('procesos_orden')->onDelete('set null');
            $table->foreign('producto_id', 'fk_movimiento_producto')->references('id')->on('productos')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
