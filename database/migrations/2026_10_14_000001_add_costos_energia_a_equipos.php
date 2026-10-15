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
            $table->string('metodo_costo', 20)->default('depreciacion')->after('vida_util_meses');
            $table->decimal('consumo_kw', 12, 4)->nullable()->after('metodo_costo');
        });

        Schema::table('proceso_equipos', function (Blueprint $table) {
            $table->string('metodo_costo', 20)->default('depreciacion')->after('depreciacion_total');
            $table->decimal('potencia_kw_usada', 12, 4)->nullable()->after('metodo_costo');
            $table->decimal('tarifa_kwh_nio', 14, 6)->nullable()->after('potencia_kw_usada');
            $table->decimal('energia_kwh', 14, 4)->default(0)->after('tarifa_kwh_nio');
            $table->decimal('costo_energia_nio', 14, 2)->default(0)->after('energia_kwh');
            $table->decimal('costo_total_nio', 14, 2)->default(0)->after('costo_energia_nio');
        });

        DB::table('proceso_equipos')->update([
            'costo_total_nio' => DB::raw('depreciacion_total'),
        ]);

        Schema::create('tarifas_energia', function (Blueprint $table) {
            $table->id();
            $table->decimal('tarifa_kwh_nio', 14, 6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas_energia');

        Schema::table('proceso_equipos', function (Blueprint $table) {
            $table->dropColumn([
                'metodo_costo', 'potencia_kw_usada', 'tarifa_kwh_nio',
                'energia_kwh', 'costo_energia_nio', 'costo_total_nio',
            ]);
        });

        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn(['metodo_costo', 'consumo_kw']);
        });
    }
};
