<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `compras` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 30);
            $table->unsignedBigInteger('proveedor_id');
            $table->date('fecha');
            $table->string('numero_documento', 100)->nullable();
            $table->decimal('subtotal', 14, 2)->default(0.00);
            $table->decimal('impuesto', 14, 2)->default(0.00);
            $table->decimal('total', 14, 2)->default(0.00);
            $table->unsignedBigInteger('moneda_id');
            $table->integer('estado')->default(1);
            $table->text('observaciones')->nullable();
            $table->unique(['codigo'], 'codigo');
            $table->foreign('moneda_id', 'fk_compra_moneda')->references('id')->on('monedas')->onDelete('restrict');
            $table->foreign('proveedor_id', 'fk_compra_proveedor')->references('id')->on('proveedores')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
