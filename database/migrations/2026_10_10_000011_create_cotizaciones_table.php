<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 30)->unique('uq_cotizacion_codigo');
            $table->unsignedBigInteger('cliente_id');
            $table->date('fecha');
            $table->date('fecha_vencimiento');
            $table->text('descripcion')->nullable();
            $table->decimal('peso_mineral', 12, 3);
            $table->string('unidad_peso', 40);
            $table->unsignedBigInteger('unidad_peso_id');
            $table->unsignedBigInteger('tipo_participacion_id');
            $table->decimal('porcentaje_cliente', 7, 4)->nullable();
            $table->decimal('porcentaje_empresa', 7, 4)->nullable();
            $table->decimal('tarifa_servicio', 14, 2)->nullable();
            $table->unsignedBigInteger('moneda_id');
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('borrador');
            $table->unsignedBigInteger('orden_trabajo_id')->nullable()->unique('uq_cotizacion_orden');
            $table->timestamps();
            $table->softDeletes('deleted_at');

            $table->foreign('cliente_id', 'fk_cotizacion_cliente')
                ->references('id')->on('clientes')->onDelete('restrict');
            $table->foreign('unidad_peso_id', 'fk_cotizacion_unidad')
                ->references('id')->on('unidades_medida')->onDelete('restrict');
            $table->foreign('tipo_participacion_id', 'fk_cotizacion_tipo_participacion')
                ->references('id')->on('tipos_participacion')->onDelete('restrict');
            $table->foreign('moneda_id', 'fk_cotizacion_moneda')
                ->references('id')->on('monedas')->onDelete('restrict');
            $table->foreign('orden_trabajo_id', 'fk_cotizacion_orden')
                ->references('id')->on('ordenes_trabajo')->onDelete('restrict');

            $table->index(['estado', 'fecha_vencimiento'], 'ix_cotizacion_estado_vencimiento');
            $table->index(['cliente_id', 'fecha'], 'ix_cotizacion_cliente_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
