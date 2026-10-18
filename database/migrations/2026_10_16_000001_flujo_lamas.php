<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acuerdos_orden', function (Blueprint $table) {
            $table->string('lama_destino', 20)->default('sin_definir');
            $table->decimal('lama_porcentaje_empresa', 7, 4)->nullable();
        });
        Schema::create('lama_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_origen_id')->constrained('ordenes_trabajo')->restrictOnDelete();
            $table->foreignId('acuerdo_id')->constrained('acuerdos_orden')->restrictOnDelete();
            $table->date('fecha');
            $table->decimal('cantidad_estimada', 12, 3);
            $table->foreignId('unidad_id')->constrained('unidades_medida')->restrictOnDelete();
            $table->string('unidad', 100);
            $table->string('ubicacion', 200);
            $table->string('destino', 20);
            $table->decimal('porcentaje_empresa', 7, 4);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->unsignedBigInteger('cliente_id')->nullable()->change();
            $table->string('tipo_trabajo', 25)->default('servicio');
            $table->foreignId('lama_lote_id')->nullable()->unique()->constrained('lama_lotes')->restrictOnDelete();
        });
        Schema::table('valoraciones_oro', function (Blueprint $table) {
            $table->decimal('valor_nio', 18, 2)->nullable();
        });
        // Las valoraciones en moneda base no necesitan reconstruir tipos de cambio históricos.
        DB::statement('UPDATE valoraciones_oro v JOIN monedas m ON m.id = v.moneda_id SET v.valor_nio = v.valor WHERE m.es_moneda_base = 1');
    }

    public function down(): void
    {
        Schema::table('valoraciones_oro', fn (Blueprint $table) => $table->dropColumn('valor_nio'));
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lama_lote_id');
            $table->dropColumn('tipo_trabajo');
        });
        // Se conserva cliente_id nullable: podrían existir trabajos internos sin cliente.
        Schema::dropIfExists('lama_lotes');
        Schema::table('acuerdos_orden', fn (Blueprint $table) => $table->dropColumn(['lama_destino', 'lama_porcentaje_empresa']));
    }
};
