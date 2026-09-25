<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `detalle_cobros` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('detalle_cobros', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cobro_id');
            $table->unsignedBigInteger('ingreso_id');
            $table->decimal('monto', 14, 2)->default(0.00);
            $table->text('observaciones')->nullable();
            $table->foreign('cobro_id', 'fk_detalle_cobro')->references('id')->on('cobros')->onDelete('cascade');
            $table->foreign('ingreso_id', 'fk_detalle_ingreso')->references('id')->on('ingresos')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_cobros');
    }
};
