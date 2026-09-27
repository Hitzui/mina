<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Equipos usados en los procesos y su asignacion a cada proceso.
 *
 * El costo de una orden no es solo la mano de obra: un proceso consume
 * equipos, y ese uso se deprecia. La asignacion lleva fecha y hora de
 * inicio y fin porque un mismo equipo puede trabajar en varios procesos
 * de forma seguida, nunca en el mismo instante.
 *
 * La depreciacion se calcula y se guarda (depreciacion_total) en el
 * momento de registrar la asignacion. Es una foto del valor calculado,
 * no un total vivo: si manana se corrige la vida util de un equipo, los
 * costos de los procesos ya cerrados no deben cambiar. Es el mismo
 * criterio que aplica al tipo de cambio historico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();

            // Codigo de negocio del equipo, por ejemplo "MOL-001"
            $table->string('codigo', 30)->unique();

            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable();

            $table->date('fecha_adquisicion')->nullable();

            // Importe en la moneda de la operacion
            $table->decimal('valor_adquisicion', 14, 2)->default(0);
            $table->decimal('valor_residual', 14, 2)->default(0);

            // Meses que el equipo sigue aportando valor desde su
            // adquisicion. De ahi sale la tasa diaria de depreciacion.
            $table->unsignedSmallInteger('vida_util_meses')->default(60);

            // Cuanto le queda por depreciar, para no bajar de cero
            $table->decimal('depreciacion_acumulada', 14, 2)->default(0);

            $table->tinyInteger('estado')->default(1);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('proceso_equipos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('proceso_orden_id');
            $table->unsignedBigInteger('equipo_id');

            /*
             * Periodo de uso. Con hora y minuto porque un molino puede
             * pasar de un proceso a otro el mismo dia. fecha_fin en NULL
             * significa que el equipo sigue asignado a ese proceso.
             */
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();

            /*
             * Foto de la depreciacion de este periodo, en la moneda del
             * equipo. Se guarda calculada y no se recalcula al vuelo, para
             * que el costo de un proceso ya registrado no cambie si despues
             * se toca la vida util o el valor del equipo.
             */
            $table->decimal('depreciacion_total', 14, 2)->default(0);

            $table->string('observaciones', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
             * Un equipo no puede estar en dos procesos a la vez. Este
             * indice es la red de seguridad de la base; la validacion
             * completa, que ademas mira los otros periodos del mismo
             * equipo, va en el modelo.
             */
            $table->index(
                ['equipo_id', 'fecha_inicio', 'fecha_fin'],
                'idx_equipo_periodo'
            );

            $table->index('proceso_orden_id', 'idx_equipo_proceso');
            $table->index('equipo_id', 'idx_equipo_equipo');
        });

        /*
         | Las claves foraneas se anaden con SQL explicito y no con
         | $table->foreign()->name().
         |
         | En Laravel 13.33.0 el encadenado ->name() sobre una clave
         | foranea es un no-op silencioso: la migracion termina bien, se
         | avisa por consola que corrio, y la restricucion no existe. Sin
         | ->name() si funciona, pero entonces el nombre lo inventa el
         | motor y el resto del proyecto usa nombres fk_* conocidos, asi
         | que conviene fijarlos a mano.
         */
        DB::statement(
            'ALTER TABLE `proceso_equipos` ADD CONSTRAINT `fk_equipo_proceso` '
            . 'FOREIGN KEY (`proceso_orden_id`) REFERENCES `procesos_orden` (`id`) '
            . 'ON DELETE CASCADE ON UPDATE RESTRICT'
        );

        /*
         * RESTRICT a proposito: un equipo con historial de procesos no se
         * puede borrar. Habria que quitar antes las asignaciones, que son
         * parte de la trazabilidad economica.
         */
        DB::statement(
            'ALTER TABLE `proceso_equipos` ADD CONSTRAINT `fk_equipo_equipo` '
            . 'FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) '
            . 'ON DELETE RESTRICT ON UPDATE RESTRICT'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('proceso_equipos');
        Schema::dropIfExists('equipos');
    }
};
