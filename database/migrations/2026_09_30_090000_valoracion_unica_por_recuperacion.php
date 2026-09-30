<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una recuperacion no puede tener dos valoraciones.
 *
 * La tabla no tenia ningun indice, y con la pantalla nueva eso es un problema
 * concreto: los gramos de una partida tienen un valor, no dos. Si se pudiera
 * insertar dos filas para la misma recuperacion, quedarian dos cifras distintas
 * para los mismos gramos —una de un dia y otra de otro— y ningun sitio
 * diria cual es la buena. Es el mismo problema que un dia con dos tipos de
 * cambio o con dos precios del oro, y por eso se resuelve igual: con un indice
 * unico en la base y no con una comprobacion en la pantalla.
 *
 * Si el banco corrige el precio de un dia, lo que se corrige es la
 * valoracion, no se añade otra. El rastro de lo que valia cada dia esta en
 * la serie de precios, que es una tabla por dia y no se toca.
 *
 * Y el indice va solo sobre la recuperacion, sin la fecha: la fecha es de la
 * valoracion, y la valoracion es una. Meterla en el indice dejaria pasar dos
 * valoraciones de la misma partida si se hicieran en dias distintos, que es
 * justo lo que se quiere impedir.
 *
 * Esta migracion SÍ cambia el esquema, asi que la version del documento
 * avanza. La tabla estaba vacia, de modo que no hay nada que dependent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('valoraciones_oro', function (Blueprint $table) {
            $table->unique(
                'recuperacion_id',
                'uq_valoracion_recuperacion'
            );
        });
    }

    public function down(): void
    {
        Schema::table('valoraciones_oro', function (Blueprint $table) {
            $table->dropUnique('uq_valoracion_recuperacion');
        });
    }
};
