<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca en el catalogo que categorias de costo calcula el sistema.
 *
 * "Mano de obra" y "Depreciacion" ya se suman solas al costo del proceso:
 * la primera desde trabajos_empleados y la segunda desde proceso_equipos.
 * Registrarlas a mano en movimientos_costos las contaria dos veces, y el
 * error no se ve en ninguna parte: el total simplemente sale mas alto.
 *
 * La marca va en la tabla y no en la pantalla a proposito. Si la regla
 * estuviera en el combo, bastaria con que alguien renombrara la categoria
 * en el catalogo para que la proteccion desapareciera en silencio; en la
 * base, el combo se limita a lo que dice la columna.
 *
 * El nombre de la categoria se busca por texto y no por id: los ids los
 * asigno el generador de la base y no significan nada para quien lea esto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias_costos', function (Blueprint $table) {
            // 1 = la calcula el sistema, no se registra a mano.
            // 0 = es un concepto que se carga desde las pantallas de costo.
            $table->tinyInteger('automatica')->default(0)->after('estado');
        });

        DB::table('categorias_costos')
            ->whereIn('nombre', ['Mano de obra', 'Depreciación', 'Depreciacion'])
            ->update(['automatica' => 1]);
    }

    public function down(): void
    {
        Schema::table('categorias_costos', function (Blueprint $table) {
            $table->dropColumn('automatica');
        });
    }
};
