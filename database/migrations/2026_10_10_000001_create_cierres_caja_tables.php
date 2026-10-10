<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierres_caja', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['caja_id', 'fecha_desde', 'fecha_hasta'], 'cierres_caja_periodo_idx');
        });

        Schema::create('detalle_cierres_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cierre_caja_id')->constrained('cierres_caja')->cascadeOnDelete();
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago')->restrictOnDelete();
            $table->foreignId('moneda_id')->constrained('monedas')->restrictOnDelete();
            $table->decimal('monto_esperado', 14, 2)->default(0);
            $table->decimal('monto_contado', 14, 2)->default(0);
            $table->decimal('diferencia', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['cierre_caja_id', 'metodo_pago_id', 'moneda_id'], 'cierre_caja_metodo_moneda_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_cierres_caja');
        Schema::dropIfExists('cierres_caja');
    }
};
