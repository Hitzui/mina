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
     * Los textos que pone DataTables, en español.
     *
     * DataTables viene en inglés y no trae un archivo de idioma español, asi
     * que los textos hay que darlos uno por uno. Lo que sale sin esto, en
     * todas las tablas de la aplicación, es:
     *
     *   - "Search:" al lado del buscador.
     *   - "Showing 1 to 10 of 20 entries" debajo de la tabla.
     *   - "No matching records found" cuando se busca algo que no esta.
     *   - "No data available in table" cuando la tabla no tiene nada.
     *   - "Previous" y "Next" en la paginación.
     *   - Y en la cabecera, un "Fecha: Activate to sort" que es lo que lee el
     *     lector de pantalla al llegar a la columna.
     *
     * Las claves son las de DataTables 2.3.8, que es la version que carga el
     * layout. Se han sacado de su propio archivo y no de memoria, porque las
     * claves han cambiado entre versiones y una clave que ya no existe no da
     * error al escribirla: simplemente no se usa, y esa parte de la pantalla se
     * queda en ingles sin que nada lo avise.
     *
     * Las que se escriben con dos puntos delante van porque DataTables las
     * pega al final del titulo de la columna, para que el titular completo sea
     * "Fecha: Activar para ordenar" y no dos cosas sueltas.
     *
     * Los separadores de miles y decimales se dejan como vienen —coma y
     * punto— y no se pasan a los de España, porque el lado de php ya
     * formatea los numeros con number_format(), que en este idioma pone la
     * coma en los miles y el punto en los decimales. Si aqui se cambiaran, un
     * 78,4521 de una celda y un 78,4521 de la informacion de abajo se
     * escribirian de forma distinta y parecerian dos numeros.
     *
     * @return array<string, mixed>
     */
    private function idiomaEspanol(): array
    {
        return [
            'search' => 'Buscar:',
            'lengthMenu' => '_MENU_ _ENTRIES_ por página',

            'info' => 'Mostrando _START_ a _END_ de _TOTAL_ _ENTRIES-TOTAL_',
            'infoEmpty' => 'Mostrando 0 a 0 de 0 _ENTRIES-TOTAL_',
            'infoFiltered' => '(de un total de _MAX_ _ENTRIES-MAX_)',

            'emptyTable' => 'No hay datos en la tabla',
            'zeroRecords' => 'Ningún registro coincide con la búsqueda',

            'loadingRecords' => 'Cargando...',
            'processing' => 'Procesando...',

            /*
             * "registros" y "registro", y no "entradas" y "entrada", que es lo
             * que pone DataTables por defecto. "Entrada" en un taller es una
             * entrada de almacen —una compra que entra—, asi que decir "10
             * entradas" al pie de una tabla de ordenes de trabajo se lee como
             * si hubiera diez compras.
             */
            'entries' => [
                '_' => 'registros',
                1 => 'registro',
            ],

            'lengthLabels' => [
                '-1' => 'Todos',
            ],

            'aria' => [
                'orderable' => ': Activar para ordenar',
                'orderableReverse' => ': Activar para invertir el orden',
                'orderableRemove' => ': Activar para quitar el orden',

                'paginate' => [
                    'first' => 'Primera página',
                    'last' => 'Última página',
                    'next' => 'Siguiente',
                    'previous' => 'Anterior',
                    'number' => '',
                ],
            ],
        ];
    }

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
         * El idioma va aqui y no en cada tabla, y por dos razones.
         *
         * La primera es que es lo unico que todas las tablas tienen en comun:
         * son veinticinco tablas y veinticinco copias de una traduccion se
         * separan en cuanto se toca una. Se queda el "Mostrando" en español en
         * veinticuatro y en ingles en la que se toco, y no hay forma de saber
         * cual de las dos es sin leerlas todas.
         *
         * Y la segunda es que hay un test que comprueba que todas las tablas
         * pasan por aqui. Con el idioma puesto en cada tabla, una tabla nueva
         * se nace en ingles y no hay nada que lo advierta.
         */
        $builder->language($this->idiomaEspanol());

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
