<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->boolean('depreciacion_activa')->default(true);
        });
        DB::table('equipos')->where('metodo_costo', 'energia')->update(['depreciacion_activa' => false]);
        DB::table('equipos')->where('metodo_costo', 'depreciacion')->update(['consumo_kw' => null]);

        Schema::create('equipo_recursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->decimal('cantidad_por_hora', 12, 4)->default(0);
            $table->unique(['equipo_id', 'producto_id']);
        });
        Schema::table('proceso_equipos', function (Blueprint $table) {
            $table->decimal('horas_efectivas', 12, 4)->nullable();
            $table->decimal('horometro_inicial', 14, 4)->nullable();
            $table->decimal('horometro_final', 14, 4)->nullable();
            $table->decimal('depreciacion_hora_nio', 18, 8)->nullable();
            $table->boolean('depreciacion_aplicada')->nullable();
            $table->json('recursos_estimados')->nullable();
            $table->boolean('consumos_confirmados')->default(false);
        });
        Schema::create('equipo_insumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('movimiento_id')->unique()->constrained('movimientos_inventario')->restrictOnDelete();
            $table->date('fecha');
            $table->decimal('cantidad', 14, 4);
            $table->decimal('disponible', 14, 4);
            $table->decimal('costo_unitario_nio', 18, 4);
            $table->timestamps();
        });
        Schema::create('equipo_consumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_insumo_id')->constrained('equipo_insumos')->restrictOnDelete();
            $table->foreignId('proceso_equipo_id')->constrained('proceso_equipos')->restrictOnDelete();
            $table->foreignId('movimiento_id')->nullable()->constrained('movimientos_inventario')->restrictOnDelete();
            $table->string('tipo', 15); // consumo o devolución al almacén
            $table->date('fecha');
            $table->decimal('cantidad', 14, 4);
            $table->decimal('costo_total_nio', 14, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipo_consumos');
        Schema::dropIfExists('equipo_insumos');
        Schema::table('proceso_equipos', fn (Blueprint $table) => $table->dropColumn([
            'horas_efectivas', 'horometro_inicial', 'horometro_final', 'depreciacion_hora_nio',
            'depreciacion_aplicada', 'recursos_estimados', 'consumos_confirmados',
        ]));
        Schema::dropIfExists('equipo_recursos');
        Schema::table('equipos', fn (Blueprint $table) => $table->dropColumn('depreciacion_activa'));
    }
};
