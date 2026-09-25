<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `cierres_orden` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('cierres_orden', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 30);
            $table->unsignedBigInteger('orden_trabajo_id');
            $table->date('fecha_cierre');
            $table->decimal('total_costos', 14, 2)->default(0.00);
            $table->decimal('total_ingresos', 14, 2)->default(0.00);
            $table->decimal('utilidad', 14, 2)->default(0.00);
            $table->decimal('total_cobrado', 14, 2)->default(0.00);
            $table->decimal('saldo_pendiente', 14, 2)->default(0.00);
            $table->decimal('gramos_recuperados', 12, 4)->default(0.0000);
            $table->text('observaciones')->nullable();
            $table->unique(['codigo'], 'codigo');
            $table->unique(['orden_trabajo_id'], 'uq_cierre_orden');
            $table->foreign('orden_trabajo_id', 'fk_cierre_orden')->references('id')->on('ordenes_trabajo')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierres_orden');
    }
};
