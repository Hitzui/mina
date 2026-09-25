<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `tipos_cambio` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('tipos_cambio', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('fecha');
            $table->unsignedBigInteger('moneda_id');
            $table->decimal('valor', 14, 6);
            $table->string('fuente', 100)->nullable();
            $table->string('observaciones', 255)->nullable();
            $table->unique(['fecha', 'moneda_id'], 'uq_tipo_cambio_fecha_moneda');
            $table->foreign('moneda_id', 'fk_tipo_cambio_moneda')->references('id')->on('monedas')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_cambio');
    }
};
