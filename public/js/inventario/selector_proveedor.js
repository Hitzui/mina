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
 * La tabla del catalogo se construye la primera vez que se abre el modal, y
 * no al cargar la pagina: mientras el modal esta cerrado, una tabla con datos
 * que nadie ve solo hace que la pantalla tarde mas. La segunda vez que se
 * abre ya esta construida y se reutiliza, con su buscador.
 */
(function ($) {
    'use strict';

    $(function () {
        const $botonBuscar = $('#btnBuscarProveedor');
        const $modal = $('#modalSeleccionarProveedor');
        const $cargador = $('#cargadorSelectorProveedor');

        if (!$botonBuscar.length || !$modal.length) {
            return;
        }

        const $tabla = $('#proveedor-selector-table');

        let modal = null;
        let tabla = null;

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
         * Monta la tabla de proveedores la primera vez.
         *
         * Se mira si ya esta construida con la funcion de DataTables, y no
         * con una variable nuestra, porque esa variable se pierde si la
         * pagina se vuelve a pedir desde cero y la comprobacion diria que no
         * lo esta cuando si lo esta.
         */
        function construirTabla() {
            if ($.fn.DataTable.isDataTable($tabla)) {
                return;
            }

            tabla = $tabla.DataTable();

            $tabla.removeClass('d-none');

            $cargador.addClass('d-none');
        }

        $botonBuscar.on('click', function () {
            ventana().show();
        });

        /*
         * Se construye en shown, que es cuando el modal ya se ve: hacerlo al
         * abrir (showing) calcula el ancho de las columnas con la ventana
         * todavia en transicion, y la tabla sale con las columnas todas
         * iguales de largas.
         */
        $modal.on('shown.bs.modal', function () {
            construirTabla();

            if (tabla) {
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
})();
