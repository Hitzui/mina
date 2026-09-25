<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `acuerdos_orden` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('acuerdos_orden', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('orden_trabajo_id');
            $table->unsignedBigInteger('tipo_participacion_id');
            $table->decimal('porcentaje_cliente', 7, 4)->nullable();
            $table->decimal('porcentaje_empresa', 7, 4)->nullable();
            $table->decimal('tarifa_servicio', 14, 2)->nullable();
            $table->unsignedBigInteger('moneda_id');
            $table->text('observaciones')->nullable();
            $table->integer('estado')->default(1);
            $table->foreign('moneda_id', 'fk_acuerdo_moneda')->references('id')->on('monedas')->onDelete('restrict');
            $table->foreign('orden_trabajo_id', 'fk_acuerdo_orden')->references('id')->on('ordenes_trabajo')->onDelete('cascade');
            $table->foreign('tipo_participacion_id', 'fk_acuerdo_tipo')->references('id')->on('tipos_participacion')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acuerdos_orden');
    }
};
