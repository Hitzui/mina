<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `monedas` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('monedas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 3);
            $table->string('nombre', 50);
            $table->string('simbolo', 10)->nullable();
            $table->boolean('es_moneda_base')->default(0);
            $table->boolean('estado')->default(1);
            $table->unique(['codigo'], 'uq_moneda_codigo');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monedas');
    }
};
