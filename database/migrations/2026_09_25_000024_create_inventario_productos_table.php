<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `inventario_productos` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('inventario_productos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('producto_id');
            $table->decimal('cantidad_actual', 14, 3)->default(0.000);
            $table->decimal('valor_actual', 14, 2)->default(0.00);
            $table->decimal('cpp_actual', 14, 4)->default(0.0000);
            $table->unique(['producto_id'], 'uq_inventario_producto');
            $table->foreign('producto_id', 'fk_inventario_producto')->references('id')->on('productos')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_productos');
    }
};
