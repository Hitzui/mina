/**
 * Elegir el proveedor de una compra, en un modal.
 *
 * El boton de cada fila del catalogo trae el id, el codigo y el nombre en
 * atributos, asi que al pulsarlo no hay que volver a preguntar al servidor
 * nada: el formulario se rellena con lo que ya venia en el boton.
 *
 * Al formulario solo se le pasa el id, que es lo que se guarda. El nombre va
 * en un campo de texto que no se manda, y es solo para que el usuario vea a
 * quien le esta comprando. Si se guardara el nombre, cambiar el nombre de un
 * proveedor dejaria las compras viejas apuntando a un nombre que ya no es.
 *
 * La tabla del catalogo no se construye aqui. Yajra la construye al cargar la
 * pagina, con su propio script, y este archivo solo se ocupa de dos cosas:
 * enseñarla cuando el modal ya se ve, y reaccionar a lo que le pase.
 *
 * Esa distincion es la que se perdio al principio, cuando este archivo
 * montaba la tabla por su cuenta. Al abrir el modal se encontraba con que la
 * tabla ya existia, volvia sin hacer nada, y el girador se quedaba girando
 * para siempre con la lista sin aparecer y sin un error en la consola. La segunda vez que se
 * abre ya esta construida y se reutiliza, con su buscador.
 */
(function ($) {
    'use strict';

    /*
     * El jQuery se pasa desde fuera, al final del archivo, con
     * (function ($) { ... })(window.jQuery). Si se cierra con los parentesis
     * vacios, aunque el jQuery este cargado, $ vale undefined dentro y el
     * primer $ que se use revienta con un "$ is not a function". Es un fallo
     * facil de volver a introducir, porque escribirlo asi parece correcto.
     *
     * Sin jQuery no se puede hacer nada de lo que hay en este archivo.
     *
     * Antes de que el jQuery se cargara del proyecto venia de una CDN, y sin
     * internet no llegaba. Entonces salia un "$ is not a function" con un
     * numero de linea que no decia nada: no decia que faltaba el jQuery, ni
     * que la pagina no iba a funcionar, ni que habia que recargar. Con este
     * aviso se dice en voz alta, que es la diferencia entre un fallo que se
     * busca solo y uno que se tiene que adivinar.
     */
    if (typeof window.jQuery === 'undefined') {
        console.error(
            'No se encontro jQuery, asi que este script no puede funcionar. '
            + 'Compruebe que el archivo public/plugins/jquery/jquery.min.js '
            + 'este ahi y que la pagina se haya recargado sin la cache.'
        );

        return;
    }

    $(function () {
        const $botonBuscar = $('#btnBuscarProveedor');
        const $modal = $('#modalSeleccionarProveedor');
        const $cargador = $('#cargadorSelectorProveedor');

        if (!$botonBuscar.length || !$modal.length) {
            return;
        }

        const $tabla = $('#proveedor-selector-table');
        const $sinSesion = $('#avisoSinSesion');

        let modal = null;

        /**
         * La ventana del modal, de bootstrap.
         *
         * Se guarda en una variable porque crear una instancia nueva cada
         * vez que se abre la deja en un estado raro: el modal aparece a medias
         * y el fondo no se oscurece.
         */
        function ventana() {
            if (modal === null) {
                modal = new bootstrap.Modal($modal[0]);
            }

            return modal;
        }

        /**
         * La tabla de proveedores, o null si todavia no esta.
         *
         * No se construye aqui. Yajra mete en la pagina un script que, al
         * terminar de cargar el documento, ya la ha montado:
         *
         *     $("#proveedor-selector-table").DataTable({...});
         *
         * Esa es la via de este proyecto para todas las tablas, y montarla
         * otra vez a mano no hacia nada: la segunda vez se encuentra con que
         * ya es una tabla de DataTables y se sale, dejando el girador
         * puesto y la lista sin aparecer. Eso fue lo que paso.
         *
         * Lo que si hace es coger la instancia que Yajra deja publicada, para
         * poder reajustar las columnas cuando el modal ya se ve. Y si de
         * verdad no esta, la construye, por si en el futuro se quita lo que
         * escribe Yajra.
         */
        function tablaDeProveedores() {
            const publicada = window.LaravelDataTables?.['proveedor-selector-table'];

            if (publicada) {
                return publicada;
            }

            /*
             * Solo se llega aqui si en el futuro se quita lo que escribe
             * Yajra. Con DataTables 2, llamar a DataTable() dos veces sobre el
             * mismo nodo deja la tabla a medias, asi que antes se comprobaba
             * con isDataTable, pero los dos caminos llamaban igual y la
             * comprobacion no hacia nada. Ahora solo hay un camino.
             */
            return $tabla.DataTable();
        }

        $botonBuscar.on('click', function () {
            ventana().show();
        });

        /*
         * Se escucha el fallo de la peticion de la tabla en el elemento que la
         * contiene, con delegacion, y no en el boton: las filas las dibuja
         * DataTables despues de que esta pagina este cargada, y en el momento
         * de registrar el manejador todavia no existen.
         */
        $modal.on('error.dt', 'table', function (evento, settings, techNote, message) {
            const xhr = techNote?.jqXHR;

            if (xhr && xhr.status === 401) {
                avisarDeSesionCerrada();

                return;
            }

            $sinSesion
                .removeClass('d-none')
                .text(
                    'No se pudo cargar la lista de proveedores. Cierra esta '
                    + 'ventana y vuelve a abrirla.'
                );
        });

        // En cuanto la peticion va bien, el aviso sobra
        $modal.on('xhr.dt', 'table', function () {
            quitarElAvisoDeSesion();
        });

        /*
         * Se construye en shown, que es cuando el modal ya se ve: hacerlo al
         * abrir (showing) calcula el ancho de las columnas con la ventana
         * todavia en transicion, y la tabla sale con las columnas todas
         * iguales de largas.
         */
        /**
         * Lo que se enseña cuando la peticion vuelve sin sesion.
         *
         * Sin esto, una sesion cerrada deja el catalogo en blanco y el
         * usuario ve una lista de proveedores vacia, que en este programa no
         * es verdad. Con esto ve que tiene que entrar otra vez.
         */
        function avisarDeSesionCerrada() {
            $sinSesion
                .removeClass('d-none')
                .text(
                    'Se acabó la sesión. Vuelve a entrar en el programa y abre '
                    + 'otra vez esta ventana para buscar el proveedor.'
                );
        }

        function quitarElAvisoDeSesion() {
            $sinSesion.addClass('d-none').text('');
        }

        $modal.on('shown.bs.modal', function () {
            /*
             * El girador se quita siempre, se haya construido la tabla o no.
             * Antes solo se quitaba en el camino que nunca se tomaba, y por
             * eso se quedaba girando para siempre con la lista sin salir y
             * sin un solo error en la consola.
             */
            $cargador.addClass('d-none').empty();

            const tabla = tablaDeProveedores();

            if (tabla && tabla.columns) {
                tabla.columns.adjust();
            }
        });

        /*
         * El boton Elegir de cada fila. Va con delegacion en el documento
         * porque las filas las dibuja la tabla despues de que esta pagina
         * este cargada, y en el momento de registrar el manejador todavia no
         * existen.
         */
        $(document).on('click', '.btn-seleccionar-proveedor', function () {
            const $elegido = $(this);

            $('#proveedor_id').val($elegido.data('id'));

            $('#proveedor_nombre').val(
                $elegido.data('nombre') + ' (' + $elegido.data('codigo') + ')'
            );

            limpiarError();

            ventana().hide();
        });

        /**
         * Quita el error de "elija el proveedor", que ya no va a salir.
         */
        function limpiarError() {
            $('#proveedor_nombre').removeClass('is-invalid');

            $('#proveedorError').addClass('d-none').empty();
        }
    });
})(window.jQuery);
