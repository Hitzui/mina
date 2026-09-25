<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `precios_oro` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('precios_oro', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('fecha');
            $table->decimal('precio', 14, 2);
            $table->string('unidad', 20)->default("gramo");
            $table->unsignedBigInteger('moneda_id');
            $table->string('fuente', 150)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreign('moneda_id', 'fk_precio_oro_moneda')->references('id')->on('monedas')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_oro');
    }
};
