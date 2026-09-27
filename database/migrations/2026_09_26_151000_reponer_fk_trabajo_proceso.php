<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repone la clave foranea fk_trabajo_proceso de trabajos_empleados.
 *
 * La migracion que movio los trabajos dentro de los proceso la elimino con
 * $table->dropForeign() y laintendedo recrear con
 * $table->foreign()->name('fk_trabajo_proceso'). En Laravel 13.33.0 ese
 * encadenado con ->name() es un no-op silencioso: la migracion se aplico,
 * la columna quedo NOT NULL, pero la restriccion no se creo.
 *
 * Sin esta correccion, un id de proceso inexistente podria colarse en la
 * columna, que es justo lo que la clave debia impedir.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Por si ya estuviera ahi (por ejemplo, en otra instalacion)
        if ($this->existe('fk_trabajo_proceso')) {
            return;
        }

        // Se comprueba que no haya datos que la impidan crear
        $huerfanos = DB::table('trabajos_empleados')
            ->whereNotNull('proceso_orden_id')
            ->whereNotIn('proceso_orden_id', DB::table('procesos_orden')->pluck('id'))
            ->count();

        if ($huerfanos > 0) {
            throw new RuntimeException(
                "Hay $huerfanos trabajo(s) apuntando a un proceso que no existe. "
                . 'Corrígelos antes de continuar.'
            );
        }

        DB::statement(
            'ALTER TABLE `trabajos_empleados` ADD CONSTRAINT `fk_trabajo_proceso` '
            . 'FOREIGN KEY (`proceso_orden_id`) REFERENCES `procesos_orden` (`id`) '
            . 'ON DELETE CASCADE ON UPDATE RESTRICT'
        );
    }

    public function down(): void
    {
        if ($this->existe('fk_trabajo_proceso')) {
            DB::statement(
                'ALTER TABLE `trabajos_empleados` DROP FOREIGN KEY `fk_trabajo_proceso`'
            );
        }
    }

    /**
     * MariaDB guarda la definicion de las claves en information_schema
     * por tabla, no por restriccion.
     */
    private function existe(string $constraint): bool
    {
        return DB::selectOne(
            'SELECT COUNT(*) AS n FROM information_schema.TABLE_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?',
            ['trabajos_empleados', $constraint]
        )->n > 0;
    }
};
