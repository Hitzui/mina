<?php

namespace App\DataTables\Concerns;

use Yajra\DataTables\Html\Builder as HtmlBuilder;

/**
 * Lo que todas las tablas de la app necesitan para verse bien.
 *
 * Existe para que el comportamiento se escriba una sola vez. Con cada
 * tabla definida en su propio archivo, los ajustes se acababan copiando
 * de una a otra, y a la larga se quedaban a medias: cuando se olvido
 * autoWidth(false) en una tabla nueva nadie lo noto, porque las demas si
 * lo traian.
 *
 * Los tres tramos de visibilidad (que se ve en un telefono, en una
 * tableta y en un escritorio) estan en las constantes de mas abajo, para
 * que el reparto sea el mismo en todas las tablas y se lea sin tener que
 * acordarse de los codigos de Bootstrap.
 */
trait TablaResponsiva
{
    /**
     * Visible siempre: lo que identifica la fila y el importe.
     *
     * Sin clase: es lo que se ve en un telefono.
     */
    public const SIEMPRE_VISIBLE = '';

    /**
     * Detalle: se oculta en telefono y tableta pequena, y aparece a
     * partir de escritorio (992px, el corte "lg" de Bootstrap).
     *
     * Descripciones, observaciones, metodo de pago y datos que se pueden
     * volver a ver entrando al detalle de la fila.
     */
    public const OCULTAR_EN_MOVIL = 'd-none d-lg-table-cell';

    /**
     * Lo menos importante: aparece solo en escritorio grande (1200px, el
     * corte "xl").
     *
     * Columnas de apoyo: unidades, estados, o el dato que ya va dentro
     * de otro.
     */
    public const OCULTAR_HASTA_ESCRITORIO = 'd-none d-xl-table-cell';

    /**
     * Marca la columna de acciones.
     *
     * La reconoce el SCSS para dejar la columna pegada al borde derecho
     * en pantallas pequenas: si hay que arrastrar toda la fila para
     * llegar al boton de eliminar, en la practica no se elimina nada.
     */
    public const COLUMNA_ACCIONES = 'columna-acciones';

    /**
     * Los ajustes que todas las tablas comparten.
     *
     * Van aparte para que cada tabla anada lo suyo sin duplicar lo de
     * las demas.
     */
    protected function ajustesComunes(HtmlBuilder $builder): HtmlBuilder
    {
        /*
         * autoWidth(false) es lo que hace que las clases de visibilidad
         * funcionen: con el ancho automatico, DataTables calcula el ancho
         * de cada columna y una columna con "display: none" deja un hueco
         * de su ancho. Desactivandolo, el ancho lo decide el CSS.
         */
        $builder->autoWidth(false);

        /*
         * Aqui no se declaran botones de exportar.
         *
         * Harian falta la extension Buttons de DataTables, y no hay build
         * compatible con el core 2.3.8 del theme: las que hay en npm llaman
         * a DataTable.ext.features.register y ese core solo tiene
         * DataTable.feature.register. Al cargarlas, la tabla reventaba.
         *
         * Declararlos sin que hagan nada seria la misma confianza falsa
         * que se acaba de quitar, asi que preferimos que no haya botones a
         * que haya botones que no responden. Ver el comentario del
         * layout, que explica el detalle.
         */
        return $builder;
    }
}
