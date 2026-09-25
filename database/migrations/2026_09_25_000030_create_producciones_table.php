<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `producciones` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('producciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('orden_trabajo_id');
            $table->unsignedBigInteger('proceso_orden_id')->nullable();
            $table->unsignedBigInteger('tipo_produccion_id');
            $table->date('fecha');
            $table->string('descripcion', 255)->nullable();
            $table->decimal('cantidad', 14, 4)->default(0.0000);
            $table->string('unidad_medida', 20);
            $table->text('observaciones')->nullable();
            $table->foreign('orden_trabajo_id', 'fk_produccion_orden')->references('id')->on('ordenes_trabajo')->onDelete('cascade');
            $table->foreign('proceso_orden_id', 'fk_produccion_proceso')->references('id')->on('procesos_orden')->onDelete('set null');
            $table->foreign('tipo_produccion_id', 'fk_produccion_tipo')->references('id')->on('tipos_produccion')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producciones');
    }
};
