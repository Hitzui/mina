<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los trabajos de los empleados pasan a vivir dentro de los procesos.
 *
 * Antes cada trabajo se colgaba de la orden de trabajo y el proceso era
 * opcional: se podía registrar trabajo "general de la OT" con
 * proceso_orden_id en NULL. Con proceso_orden_id y orden_trabajo_id los
 * dos nullable, no había ninguna restricción que atara el trabajo a algo.
 *
 * Ahora el trabajo pertenece a un proceso, y la orden de trabajo se
 * alcanza a través del proceso: el dato queda en un solo lugar y es
 * imposible que se desincronicen.
 *
 * - Se elimina la columna orden_trabajo_id y su clave foránea.
 * - proceso_orden_id pasa a NOT NULL.
 * - Los trabajos huérfanos (sin proceso) se van: ya estaban borrados
 *   lógicamente, asi que no se pierde nada visible. Se avisa igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         | Los trabajos sin proceso no tienen a donde colgarse. Antes de
         | volver la columna obligatoria hay que resolverlos, porque si no
         | el ALTER falla por la constraint.
         |
         | Solo se borran los que ya estaban eliminados logicamente: un
         | trabajo vivo sin proceso es un dato que no se puede inventar,
         | asi que se deja intacto y se avisa para que se decida.
         */
        $huerfanosVivos = DB::table('trabajos_empleados')
            ->whereNull('proceso_orden_id')
            ->whereNull('deleted_at')
            ->count();

        if ($huerfanosVivos > 0) {
            throw new RuntimeException(
                "Hay $huerfanosVivos trabajo(s) de empleado sin proceso y sin estar "
                . 'borrados lógicamente. Asignales un proceso antes de continuar: '
                . 'no se puede decidir a cuál pertenecen.'
            );
        }

        $borrados = DB::table('trabajos_empleados')
            ->whereNull('proceso_orden_id')
            ->delete();

        if ($borrados > 0) {
            fwrite(
                STDOUT,
                "  Trabajos de empleado sin proceso y ya borrados lógicamente, "
                . "eliminados: $borrados\n"
            );
        }

        // La orden de trabajo ya no se guarda en el trabajo: se lee del proceso
        Schema::table('trabajos_empleados', function ($table) {
            $table->dropForeign('fk_trabajo_orden');
            $table->dropColumn('orden_trabajo_id');
        });

        Schema::table('trabajos_empleados', function ($table) {
            $table->dropForeign('fk_trabajo_proceso');

            $table->unsignedBigInteger('proceso_orden_id')
                ->nullable(false)
                ->change();

            $table->foreign('proceso_orden_id')
                ->references('id')
                ->on('procesos_orden')
                ->onDelete('cascade')
                ->onUpdate('restrict')
                ->name('fk_trabajo_proceso');
        });
    }

    public function down(): void
    {
        Schema::table('trabajos_empleados', function ($table) {
            $table->dropForeign('fk_trabajo_proceso');

            // Vuelve a ser opcional: el proceso se puede quedar sin trabajo
            $table->unsignedBigInteger('proceso_orden_id')
                ->nullable(true)
                ->change();

            $table->unsignedBigInteger('orden_trabajo_id')
                ->nullable(true)
                ->after('empleado_id');

            $table->foreign('proceso_orden_id')
                ->references('id')
                ->on('procesos_orden')
                ->onDelete('cascade')
                ->onUpdate('restrict')
                ->name('fk_trabajo_proceso');

            // Se recupera la orden desde el proceso, para no perder el dato
            DB::statement(
                'UPDATE trabajos_empleados t
                   JOIN procesos_orden p ON p.id = t.proceso_orden_id
                    SET t.orden_trabajo_id = p.orden_trabajo_id'
            );

            $table->foreign('orden_trabajo_id')
                ->references('id')
                ->on('ordenes_trabajo')
                ->onDelete('set null')
                ->onUpdate('restrict')
                ->name('fk_trabajo_orden');
        });
    }
};
