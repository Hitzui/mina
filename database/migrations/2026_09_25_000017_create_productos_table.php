<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `productos` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 30);
            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable();
            $table->string('unidad_medida', 20);
            $table->string('categoria', 50)->nullable();
            $table->decimal('stock_minimo', 14, 3)->default(0.000);
            $table->boolean('estado')->default(1);
            $table->unique(['codigo'], 'codigo');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
