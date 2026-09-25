<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `procesos_orden` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('procesos_orden', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('orden_trabajo_id');
            $table->unsignedBigInteger('etapa_id');
            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_fin')->nullable();
            $table->decimal('peso_entrada', 12, 3)->nullable();
            $table->decimal('peso_salida', 12, 3)->nullable();
            $table->integer('estado')->default(1);
            $table->text('observaciones')->nullable();
            $table->string('codigo', 30);
            $table->unique(['orden_trabajo_id', 'codigo'], 'UK_procesos_orden');
            $table->foreign('etapa_id', 'fk_proceso_etapa')->references('id')->on('etapas')->onDelete('restrict');
            $table->foreign('orden_trabajo_id', 'fk_proceso_orden')->references('id')->on('ordenes_trabajo')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procesos_orden');
    }
};
