<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla `cobros` (esquema existente generado fuera de Laravel).
     */
    public function up(): void
    {
        Schema::create('cobros', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('caja_id');
            $table->unsignedBigInteger('metodo_pago_id');
            $table->date('fecha');
            $table->decimal('monto', 14, 2)->default(0.00);
            $table->unsignedBigInteger('moneda_id');
            $table->string('referencia', 100)->nullable();
            $table->integer('estado')->default(1);
            $table->text('observaciones')->nullable();
            $table->string('codigo', 30);
            $table->unique(['codigo'], 'codigo');
            $table->foreign('caja_id', 'fk_cobro_caja')->references('id')->on('cajas')->onDelete('restrict');
            $table->foreign('cliente_id', 'fk_cobro_cliente')->references('id')->on('clientes')->onDelete('restrict');
            $table->foreign('metodo_pago_id', 'fk_cobro_metodo')->references('id')->on('metodos_pago')->onDelete('restrict');
            $table->foreign('moneda_id', 'fk_cobro_moneda')->references('id')->on('monedas')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobros');
    }
};
