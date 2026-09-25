<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `detalle_compras` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('detalle_compras', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('compra_id');
            $table->unsignedBigInteger('producto_id');
            $table->decimal('cantidad', 14, 3);
            $table->decimal('costo_unitario', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->foreign('compra_id', 'fk_detalle_compra')->references('id')->on('compras')->onDelete('cascade');
            $table->foreign('producto_id', 'fk_detalle_producto')->references('id')->on('productos')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_compras');
    }
};
