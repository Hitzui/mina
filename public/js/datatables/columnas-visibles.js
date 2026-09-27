/**
 * Copia la clase de cada columna a su encabezado.
 *
 * Por que hace falta:
 *
 * En cada DataTable la clase de una columna se declara con addClass(), que
 * Yajra aplica a los <td> (es la opcion columns.className de DataTables).
 * Pero DataTables no tiene ninguna opcion para ponerle una clase al <th>: la
 * cabecera se pinta en el servidor y queda sin clase.
 *
 * El resultado sin este archivo es que al ocultar una columna en movil se
 * ocultan sus celdas pero su encabezado se queda, y la fila de titulos
 * queda con huecos.
 *
 * No se resuelve poniendo la clase a mano en el <th> porque la unica via
 * que ofrece Yajra para ahi (set('class', ...)) mete ademas la clave
 * "class" dentro del JSON de columns que se le pasa a DataTables, y ahi si
 * que es un parametro desconocido: en modo estricto rompe la tabla.
 *
 * Asi que se hace desde el propio DataTables, que ya sabe que clase lleva
 * cada columna, y se le pone al <th> que ya esta en el DOM. Se repite en
 * cada dibujado porque DataTables reconstruye las celdas de la cabecera al
 * ordenar o al cambiar de pagina.
 */
(function ($) {
    'use strict';

    if (typeof $ === 'undefined' || typeof $.fn.dataTable === 'undefined') {
        return;
    }

    /**
     * Pone en cada <th> las clases que su columna declara.
     *
     * @param {Object} settings Objeto de configuracion que pasa DataTables
     */
    function aplicarClasesAlEncabezado(settings) {
        var encabezado = settings.nTHead && settings.nTHead.rows && settings.nTHead.rows[0];

        if (!encabezado || !encabezado.cells) {
            return;
        }

        var columnas = settings.aoColumns || settings.columns || [];

        for (var i = 0; i < encabezado.cells.length; i++) {
            var th = encabezado.cells[i];
            var columna = columnas[i];

            if (!th || !columna) {
                continue;
            }

            var clase = columna.sClass || columna.className || '';

            if (!clase) {
                continue;
            }

            // Se comprueba antes de añadir: el evento se dispara en cada
            // dibujado y sin esto la clase se repetiria indefinidamente
            if (th.className.indexOf(clase) === -1) {
                th.className = (th.className + ' ' + clase).trim();
            }
        }
    }

    // init: cuando la tabla se crea. draw: en cada recarga, orden o cambio
    // de pagina, que es cuando DataTables rehace la fila de titulos.
    $(document).on('init.dt', 'table.dataTable', function (e, settings) {
        aplicarClasesAlEncabezado(settings);
    });

    $(document).on('draw.dt', 'table.dataTable', function (e, settings) {
        aplicarClasesAlEncabezado(settings);
    });
})(window.jQuery);
