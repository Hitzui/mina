<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `recuperaciones` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('recuperaciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('orden_trabajo_id');
            $table->date('fecha');
            $table->decimal('gramos', 12, 4)->default(0.0000);
            $table->decimal('pureza', 8, 6)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreign('orden_trabajo_id', 'fk_recuperacion_orden')->references('id')->on('ordenes_trabajo')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recuperaciones');
    }
};
