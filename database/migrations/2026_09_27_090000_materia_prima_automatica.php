<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Materia prima" pasa a ser un concepto que calcula el sistema.
 *
 * Estaba marcada como cargable a mano, porque hasta ahora no habia otra
 * forma de contabilizarla: se tecleaba el consumo de cemento o de
 * quimicos directamente en la pantalla de costos del proceso.
 *
 * Ahora el material sale del almacen: se registra una entrada y luego el
 * consumo en el proceso, y el costo sale del promedio de lo que habia
 * dentro. Dejar la categoria como manual permitiria cargar la misma
 * materia prima por los dos caminos, y el costo del proceso saldria mas
 * alto sin que se notara en ninguna parte.
 *
 * Lo que no entra por el almacen (un insumo suelto, algo menor) se lleva
 * a la categoria "Otros costos", que ya existe para lo que no encaja en
 * ninguna de las anteriores.
 *
 * El nombre se busca por texto y no por id, igual que en la migracion que
 * creo la columna: los ids los asigno el generador y no significan nada
 * para quien lea esto.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('categorias_costos')
            ->whereRaw('LOWER(TRIM(nombre)) = ?', ['materia prima'])
            ->update(['automatica' => 1]);
    }

    public function down(): void
    {
        DB::table('categorias_costos')
            ->whereRaw('LOWER(TRIM(nombre)) = ?', ['materia prima'])
            ->update(['automatica' => 0]);
    }
};
