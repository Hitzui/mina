/**
 * Recuperaciones de oro: alta, edicion y borrado.
 *
 * Sigue el camino de los proveedores y los materiales: los tres en modal
 * sobre la lista, con los datos pedidos al servidor y puestos en el modal. No
 * se copia nada del html de la fila a mano, que es como se desincroniza una
 * tabla con su detalle.
 *
 * Lo que hay aqui y no en el resto de modales, y no es un detalle de estilo:
 *
 * La pureza se manda como fracción. Al abrir el modal de editar, el valor
 * viene del servidor tal cual esta guardado —0.915—, y se escribe en el campo
 * con punto, que es lo que el campo numerico del navegador entiende. Si se
 * hiciera al reves, dividiendolo entre cien para enseñar el 91,5 %, el valor
 * que se guardaria seria 0,915 de nuevo por suerte, y con suerte: en cuanto se
 * tocara el campo se veria que el numero no cuadra y nadie sabria por que.
 *
 * Y el campo de la pureza lleva un aviso mientras se escribe. El servidor ya
 * rechaza un numero mayor que uno, y con un texto al lado que lo explica; el
 * aviso de aqui es lo mismo antes de llegar al servidor, que es la diferencia
 * entre "no se pudo guardar" y "esto va mal".
 */
(function ($) {
    'use strict';

    $(function () {
        const $formulario = $('#formRecuperacion');

        if (!$formulario.length) {
            return;
        }

        const modalFormElement = document.getElementById('modalRecuperacion');
        const modalForm = modalFormElement ? new bootstrap.Modal(modalFormElement) : null;

        const $error = $('#recuperacionError');

        // ------------------------------------------------------------------
        // Utilidades
        // ------------------------------------------------------------------

        function limpiarError() {
            $error.addClass('d-none').empty();
        }

        function limpiarFormulario() {
            $formulario[0].reset();

            $formulario.find('.is-invalid').removeClass('is-invalid');
            $formulario.find('.invalid-feedback').hide().text('');

            $('#fechaRecuperacion').val('');
            $('#gramos').val('');
            $('#pureza').val('');
            $('#observaciones').val('');

            /*
             * El desplegable de la orden se limpia a mano y no con reset(),
             * porque el reset no sabe lo que hay dentro de un select2: deja
             * escrito en pantalla el valor anterior mientras el campo real ya
             * esta vacio. Al abrir el modal de nuevo se veria "OT-2026-0001" y
             * al guardar se mandaria esa orden con la fecha de hoy, que es
             * justo una recuperacion de la orden equivocada.
             */
            if ($.fn.select2 && $('#orden_trabajo_id').data('select2')) {
                $('#orden_trabajo_id').val(null).trigger('change');
            } else {
                $('#orden_trabajo_id').val('');
            }

            $formulario.find('input[name="_method"]').remove();

            ocultarAvisoDePureza();
        }

        /**
         * El mensaje del servidor, marcando el campo que fallo.
         *
         * Un error de validacion viene con un campo concreto. Se marca ese
         * campo, y no se suelta la lista en un bloque aparte, para que se vea
         * cual es sin tener que ir a buscarlo.
         *
         * El caso importante aqui es el de la orden cerrada: el servidor lo
         * manda contra el campo de la orden, que es donde el usuario lo ha
         * elegido, y el texto es largo y dice por que y que se puede hacer.
         * Si ese texto se soltara en un bloque arriba, con el separador de
         * lineas, seguiria siendo el mismo texto pero con saltos en medio de
         * las frases, que es como se lee peor.
         */
        function mostrarError(xhr) {
            const respuesta = xhr.responseJSON || {};

            if (!respuesta.errors) {
                $error
                    .removeClass('d-none')
                    .html(respuesta.message || 'No se pudo guardar la recuperación.');

                return;
            }

            let marcados = 0;

            $formulario.find('.is-invalid').removeClass('is-invalid');
            $formulario.find('.invalid-feedback').hide().text('');

            Object.entries(respuesta.errors).forEach(function (par) {
                const campo = par[0];
                const mensajes = par[1];

                const $campo = $formulario.find('[name="' + campo + '"]');

                if ($campo.length === 0) {
                    return;
                }

                $campo.addClass('is-invalid');

                $campo
                    .siblings('.invalid-feedback')
                    .first()
                    .text(Array.isArray(mensajes) ? mensajes[0] : mensajes)
                    .show();

                marcados++;
            });

            if (marcados === 0) {
                $error
                    .removeClass('d-none')
                    .html(Object.values(respuesta.errors).flat().join(' '));
            }
        }

        // ------------------------------------------------------------------
        // El aviso de la pureza, mientras se escribe
        // ------------------------------------------------------------------

        /**
         * Si el numero que se esta escribiendo cabe en una fraccion.
         *
         * Solo avisa, no bloquea: el campo se puede dejar a medias y el
         * servidor es el que manda. Aqui lo unico que se hace es no dejar que
         * se escriba el 91 pensando que es el 91 %, porque ese es el error
         * que se cuela —la columna guarda fracciones, y 91 ahi son el 9100 %—
         * y que no se ve hasta que una valoracion sale absurda.
         */
        function revisarPureza() {
            const bruto = $('#pureza').val();

            if (bruto === '' || bruto === null) {
                ocultarAvisoDePureza();

                return;
            }

            const valor = Number(String(bruto).replace(',', '.'));

            if (!isFinite(valor)) {
                ocultarAvisoDePureza();

                return;
            }

            if (valor > 1) {
                mostrarAvisoDePureza(
                    'Ese número es mayor que 1. La pureza va como fracción: '
                    + 'el 91,5 % se escribe 0,915.',
                    'warning'
                );

                return;
            }

            if (valor < 0) {
                mostrarAvisoDePureza('La pureza no puede ser negativa.', 'warning');

                return;
            }

            mostrarAvisoDePureza(
                /*
                * El separador de miles es la coma y el de decimales el punto —1,546.00—
                * porque el taller es de Nicaragua y porque es como lo escribe php con
                * number_format(), que es como lo escriben todas las tablas de la
                * aplicacion. Con el locale "es", que es el de Espana, salia 1.546,00 y
                * el modal decia una cosa y la tabla de debajo otra, para el mismo
                * numero.
                *
                * Y el locale va con el codigo del pais a proposito: "es" a secas quiere
                * decir "espanol, y por defecto el de Espana", con el punto en los miles.
                * Los seis formateadores de la aplicacion usan "es-NI" por eso, y si uno
                * se queda en "es" el descuadre vuelve sin que nada avise.
                */
                'Eso es el ' + new Intl.NumberFormat('es-NI', {
                    maximumFractionDigits: 2,
                }).format(valor * 100) + ' %.',
                'info'
            );
        }

        function mostrarAvisoDePureza(texto, tipo) {
            $('#purezaAviso')
                .removeClass('d-none alert-info alert-warning')
                .addClass('alert-' + tipo)
                .text(texto);
        }

        function ocultarAvisoDePureza() {
            $('#purezaAviso').addClass('d-none').empty();
        }

        $('#pureza').on('input', revisarPureza);

        // ------------------------------------------------------------------
        // Alta
        // ------------------------------------------------------------------

        $(document).on('click', '#btnNuevaRecuperacion', function () {
            limpiarError();
            limpiarFormulario();

            $('#modalRecuperacionLabel').text('Nueva recuperación de oro');
            $('#textoGuardarRecuperacion').text('Guardar recuperación');

            $formulario.attr('action', $formulario.data('store-url'));

            modalForm?.show();
        });

        // ------------------------------------------------------------------
        // Edicion
        // ------------------------------------------------------------------

        $(document).on('click', '.btn-edit-recuperacion', function (evento) {
            evento.preventDefault();

            const url = $(this).data('url');

            if (!url) {
                return;
            }

            $.ajax({
                url: url,
                method: 'GET',
                success: function (datos) {
                    limpiarError();
                    limpiarFormulario();

                    $('#modalRecuperacionLabel').text('Editar la recuperación');
                    $('#textoGuardarRecuperacion').text('Guardar cambios');

                    /*
                     * El method de mentira es lo que hace que un formulario
                     * normal envie un PUT. Antes estaba puesto para el alta y
                     * se quita a proposito: si se dejara, el alta llegaria como
                     * un PUT a la ruta de crear.
                     */
                    if ($formulario.find('input[name="_method"]').length === 0) {
                        $formulario.append(
                            $('<input>', {
                                type: 'hidden',
                                name: '_method',
                                value: 'PUT',
                            })
                        );
                    }

                    $formulario.attr(
                        'action',
                        $formulario.data('update-url').replace('__ID__', datos.id)
                    );

                    $('#fechaRecuperacion').val(datos.fecha ?? '');
                    $('#gramos').val(datos.gramos ?? '');
                    $('#pureza').val(datos.pureza ?? '');
                    $('#observaciones').val(datos.observaciones ?? '');

                    if ($.fn.select2 && $('#orden_trabajo_id').data('select2')) {
                        $('#orden_trabajo_id').val(datos.orden_trabajo_id ?? '').trigger('change');
                    } else {
                        $('#orden_trabajo_id').val(datos.orden_trabajo_id ?? '');
                    }

                    revisarPureza();

                    modalForm?.show();
                },
                error: function (xhr) {
                    mostrarError(xhr);
                    modalForm?.show();
                },
            });
        });

        // ------------------------------------------------------------------
        // Guardar
        // ------------------------------------------------------------------

        $formulario.on('submit', function (evento) {
            evento.preventDefault();

            limpiarError();

            const $boton = $('#btnGuardarRecuperacion');
            $boton.prop('disabled', true);

            $.ajax({
                url: $formulario.attr('action'),
                method: 'POST',
                data: $formulario.serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                success: function () {
                    $boton.prop('disabled', false);

                    modalForm?.hide();

                    recargarTabla();
                },
                error: function (xhr) {
                    $boton.prop('disabled', false);
                    mostrarError(xhr);
                },
            });
        });

        // ------------------------------------------------------------------
        // Borrar
        // ------------------------------------------------------------------

        $(document).on('submit', 'form[data-confirm-delete-recuperacion]', function (evento) {
            evento.preventDefault();

            const $formularioBorrar = $(this);
            const fecha = $formularioBorrar.data('fecha') || '';
            const gramos = $formularioBorrar.data('gramos') || '';
            const orden = $formularioBorrar.data('orden') || '';

            /*
             * El aviso dice cuantos gramos se van y de que orden son, y avisa
             * de que no se puede volver atras. Es la unica pantalla de esta
             * parte de la aplicacion de la que no hay vuelta: una recuperacion
             * borrada no tiene fecha, ni gramos, ni relacion con la orden, y el
             * valor de esa partida habria que rehacerlo a mano.
             *
             * Y aun asi se puede borrar con la orden cerrada, y se deja: un
             * numero de gramos mal tecleado hay que poder quitarlo aunque la
             * orden se cerrara en su dia.
             */
            Swal.fire({
                title: '¿Eliminar la recuperación del ' + fecha + '?',
                html: 'Se borran <strong>' + gramos + ' gramos</strong>'
                    + (orden ? ' de la orden <strong>' + orden + '</strong>' : '')
                    + '.<br><br>Esto no se puede deshacer, y el valor del oro de '
                    + 'esa partida habría que rehacerlo a mano.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            }).then(function (resultado) {
                if (!resultado.isConfirmed) {
                    return;
                }

                const $boton = $formularioBorrar.find('button[type="submit"]');

                $boton.prop('disabled', true);

                $.ajax({
                    url: $formularioBorrar.data('url'),
                    method: 'POST',
                    data: {
                        _method: 'DELETE',
                        _token: $('meta[name="csrf-token"]').attr('content'),
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    success: function () {
                        recargarTabla();
                    },
                    error: function () {
                        $boton.prop('disabled', false);
                    },
                });
            });
        });

        function recargarTabla() {
            if ($.fn.dataTable.isDataTable('#recuperaciones-table')) {
                $('#recuperaciones-table').DataTable().ajax.reload();
            } else {
                window.location.reload();
            }
        }
    });
})(jQuery);
