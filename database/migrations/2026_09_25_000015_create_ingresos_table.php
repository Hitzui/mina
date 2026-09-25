<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `ingresos` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('ingresos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('orden_trabajo_id');
            $table->unsignedBigInteger('liquidacion_id')->nullable();
            $table->unsignedBigInteger('tipo_ingreso_id');
            $table->date('fecha');
            $table->string('descripcion', 255)->nullable();
            $table->decimal('cantidad', 12, 4)->nullable();
            $table->string('unidad_medida', 20)->nullable();
            $table->decimal('precio_unitario', 14, 2)->nullable();
            $table->decimal('total', 14, 2)->default(0.00);
            $table->decimal('precio_unitario_nio', 14, 4)->nullable();
            $table->decimal('total_nio', 14, 2)->nullable();
            $table->unsignedBigInteger('moneda_id');
            $table->integer('estado')->default(1);
            $table->text('observaciones')->nullable();
            $table->foreign('liquidacion_id', 'fk_ingreso_liquidacion')->references('id')->on('liquidaciones')->onDelete('set null');
            $table->foreign('moneda_id', 'fk_ingreso_moneda')->references('id')->on('monedas')->onDelete('restrict');
            $table->foreign('orden_trabajo_id', 'fk_ingreso_orden')->references('id')->on('ordenes_trabajo')->onDelete('cascade');
            $table->foreign('tipo_ingreso_id', 'fk_ingreso_tipo')->references('id')->on('tipos_ingreso')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingresos');
    }
};
