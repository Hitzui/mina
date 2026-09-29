<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El precio del oro pasa a cuatro decimales y gana su indice unico.
 *
 * Son dos cosas que se corrigieron juntas porque las dos se deben al mismo
 * defecto de como se creo la tabla: se creo para guardar la serie de precios
 * como un dato mas, sin mirar que la tabla la lee una valoracion que multiplica
 * gramos por precio.
 *
 * 1. Mas decimales. El precio es por gramo y anda por los 80 dolares, y hay
 *    paises donde el gramo de oro fino se mueve en centavos. Con dos decimales,
 *    dos dias seguidos pueden acabar con el mismo numero, y entonces la serie
 *    deja de decir en que dia subio y en que dia se quedo. El tipo de cambio
 *    lleva seis por el mismo motivo, y con mas razon: el cordoba se mueve cada
 *    dia. Cuatro es lo minimo para que un gramo de oro pueda moverse de un
 *    dia a otro.
 *
 *    Perder nada: los precios que hubiera estan a cero en los decimales
 *    nuevos, que es su valor exacto.
 *
 * 2. Indice unico por fecha, unidad y moneda. Un dia no puede tener dos
 *    precios del gramo, por la misma razon que un dia no puede tener dos tipos
 *    de cambio: la fila se usa para valorar, y si un dia tuviera dos precios
 *    habria que elegir uno sin que nada dijera cual, con lo que el valor de
 *    una misma recuperacion saldria distinto segun por donde se mirara.
 *
 *    El indice lleva la unidad y no solo la fecha, porque el gramo y la onza
 *    pueden convivir el mismo dia sin pelear: son numeros que se parecen mucho
 *    —uno por debajo de cien, el otro por encima de dos mil— y metidos juntos
 *    en la misma serie alguien acabaria multiplicando gramos por onzas sin
 *    darse cuenta. Separados por la unidad en el indice, conviven sin que
 *    ninguna pise a la otra, y quien valore tiene que decir cual esta usando.
 *
 * Y lleva la moneda porque un taller que compra en dolares y contabiliza en
 * cordobas necesita las dos: el precio en dolares del banco y su equivalencia
 * en cordoba con el tipo de cambio de ese dia. Un dia con dos monedas son dos
 * datos distintos, no un duplicado.
 *
 * Esta migracion SÍ cambia el esquema, asi que la version del documento avanza:
 * cambia la definicion de una columna y se anade un indice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precios_oro', function (Blueprint $table) {
            $table->decimal('precio', 14, 4)->change();
        });

        Schema::table('precios_oro', function (Blueprint $table) {
            $table->unique(
                ['fecha', 'unidad', 'moneda_id'],
                'uq_precio_oro_fecha_unidad_moneda'
            );
        });
    }

    public function down(): void
    {
        Schema::table('precios_oro', function (Blueprint $table) {
            $table->dropUnique('uq_precio_oro_fecha_unidad_moneda');
        });

        Schema::table('precios_oro', function (Blueprint $table) {
            $table->decimal('precio', 14, 2)->change();
        });
    }
};
