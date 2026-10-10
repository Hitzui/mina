<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CAMPOS = [
        'productos' => 'unidad_medida',
        'ordenes_trabajo' => 'unidad_peso',
        'producciones' => 'unidad_medida',
        'ingresos' => 'unidad_medida',
        'precios_oro' => 'unidad',
        'trabajos_empleados' => 'unidad',
    ];

    public function up(): void
    {
        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 40)->unique();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        $nombres = collect([
            'toneladas', 'kg', 'gramos', 'hora', 'gramo', 'onza troy', 'quilogramo',
            'g', 'und', 'Bolsas', 'litro', 'día', 'unidad', 'tarea',
        ]);
        foreach (self::CAMPOS as $tabla => $campo) {
            $nombres = $nombres->merge(DB::table($tabla)->whereNotNull($campo)->distinct()->pluck($campo));
        }
        $nombres = $nombres->map(fn ($nombre) => trim((string) $nombre))->filter()->unique()->values();

        foreach ($nombres as $nombre) {
            DB::table('unidades_medida')->insert([
                'nombre' => $nombre,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (self::CAMPOS as $tabla => $campo) {
            $fk = $campo.'_id';
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $columna = $tabla === 'ordenes_trabajo' ? 'unidad_peso_id' : ($tabla === 'precios_oro' || $tabla === 'trabajos_empleados' ? 'unidad_id' : 'unidad_medida_id');
                $table->unsignedBigInteger($columna)->nullable()->index();
                $table->foreign($columna, 'fk_'.$tabla.'_unidad')->references('id')->on('unidades_medida')->onDelete('restrict');
            });

            $columna = $tabla === 'ordenes_trabajo' ? 'unidad_peso_id' : ($tabla === 'precios_oro' || $tabla === 'trabajos_empleados' ? 'unidad_id' : 'unidad_medida_id');
            DB::table($tabla)->whereNotNull($campo)->orderBy('id')->chunk(500, function ($rows) use ($tabla, $campo, $columna) {
                foreach ($rows as $row) {
                    $unidadId = DB::table('unidades_medida')->where('nombre', trim((string) $row->{$campo}))->value('id');
                    if ($unidadId) {
                        DB::table($tabla)->where('id', $row->id)->update([$columna => $unidadId]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::CAMPOS as $tabla => $campo) {
            $columna = $tabla === 'ordenes_trabajo' ? 'unidad_peso_id' : ($tabla === 'precios_oro' || $tabla === 'trabajos_empleados' ? 'unidad_id' : 'unidad_medida_id');
            Schema::table($tabla, function (Blueprint $table) use ($tabla, $columna) {
                $table->dropForeign('fk_'.$tabla.'_unidad');
                $table->dropIndex([$columna]);
                $table->dropColumn($columna);
            });
        }
        Schema::dropIfExists('unidades_medida');
    }
};
