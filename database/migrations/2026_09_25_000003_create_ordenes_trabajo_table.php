<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `ordenes_trabajo` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('ordenes_trabajo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 30);
            $table->unsignedBigInteger('cliente_id');
            $table->date('fecha');
            $table->text('descripcion')->nullable();
            $table->decimal('peso_mineral', 12, 3)->default(0.000);
            $table->string('unidad_peso', 10)->default("kg");
            $table->integer('estado')->default(1);
            $table->unique(['codigo'], 'codigo');
            $table->foreign('cliente_id', 'fk_orden_cliente')->references('id')->on('clientes')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_trabajo');
    }
};
