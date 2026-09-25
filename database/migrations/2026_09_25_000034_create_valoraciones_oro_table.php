<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `valoraciones_oro` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('valoraciones_oro', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recuperacion_id');
            $table->unsignedBigInteger('precio_oro_id')->nullable();
            $table->decimal('valor', 14, 2);
            $table->unsignedBigInteger('moneda_id');
            $table->date('fecha');
            $table->text('observaciones')->nullable();
            $table->foreign('moneda_id', 'fk_valoracion_moneda')->references('id')->on('monedas')->onDelete('restrict');
            $table->foreign('precio_oro_id', 'fk_valoracion_precio')->references('id')->on('precios_oro')->onDelete('restrict');
            $table->foreign('recuperacion_id', 'fk_valoracion_recuperacion')->references('id')->on('recuperaciones')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valoraciones_oro');
    }
};
