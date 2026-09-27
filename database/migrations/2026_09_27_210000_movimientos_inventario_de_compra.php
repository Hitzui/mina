<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anade a movimientos_inventario de que compra viene el movimiento.
 *
 * El almacen y las compras son la misma operacion partida en dos: cuando se
 * finaliza una compra, el material entra por el kardex. Para poder deshacerlo
 * hace falta saber quais movimientos salieron de cada compra, y buscarlo por
 * el texto de "referencia" no sirve: ese campo lo escribe quien registra el
 * movimiento, y un "COMPRA:CMP-000001" tecleado a mano en una entrada normal
 * se confundiria con el de una compra de verdad.
 *
 * Con la columna la busqueda es exacta y no depende de que nadie escriba un
 * texto con una forma concreta.
 *
 * Va en NULL cuando el movimiento no viene de una compra: las entradas
 * manuales y los consumos en procesos la dejan vacia.
 *
 * Al eliminar la compra, la columna se queda en NULL y el movimiento sigue
 * como estaba, en vez de desaparecer con ella. Si se borrara en cascada, el
 * kardex perderia la entrada y el almacen quedaria descuadrado sin que nadie
 * lo pidiera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->unsignedBigInteger('compra_id')
                ->nullable()
                ->after('proceso_orden_id');

            $table->index('compra_id');
        });

        // La clave foranea se agrega aparte, porque en este proyecto
        // Blueprint::foreign()->name() descarta la restriccion en silencio.
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->foreign('compra_id')
                ->references('id')
                ->on('compras')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropForeign(['compra_id']);
            $table->dropColumn('compra_id');
        });
    }
};
