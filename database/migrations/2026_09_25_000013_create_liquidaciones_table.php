<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `liquidaciones` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('orden_trabajo_id');
            $table->date('fecha');
            $table->decimal('gramos_recuperados', 12, 4)->default(0.0000);
            $table->decimal('gramos_cliente', 12, 4)->default(0.0000);
            $table->decimal('gramos_empresa', 12, 4)->default(0.0000);
            $table->decimal('porcentaje_cliente', 7, 4)->default(0.0000);
            $table->decimal('porcentaje_empresa', 7, 4)->default(0.0000);
            $table->text('observaciones')->nullable();
            $table->foreign('orden_trabajo_id', 'fk_liquidacion_orden')->references('id')->on('ordenes_trabajo')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones');
    }
};
