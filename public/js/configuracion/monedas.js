/**
 * Monedas: alta, edicion, ficha y baja.
 *
 * Los tres van en modal sobre la lista, igual que los proveedores y los
 * materiales. Lo que hay aqui y no en el tipo de cambio es la ficha, que
 * ademas de los cinco campos enseña donde se usa la moneda, porque esa es la
 * pregunta que uno se hace al mirar un catalogo y la fila no la contesta.
 *
 * El boton de eliminar no desaparece cuando la moneda esta en uso: el aviso
 * lo dice el servidor, que es el unico que sabe si algo la esta usando, y el
 * texto de ese aviso trae el dato concreto ("esta en 2 compras y 30 tipos de
 * cambio"), que es lo que hace util el mensaje.
 *
 * Todo se rellena desde el servidor: los botones de la fila llevan su url en
 * un data-url, y el javascript pide los datos y los pone. No se copia nada del
 * html de la fila a mano, que es como se desincroniza una tabla con su
 * detalle.
 */
(function ($) {
    'use strict';

    $(function () {
        const $formulario = $('#formMoneda');

        if (!$formulario.length) {
            return;
        }

        const modalFormElement = document.getElementById('modalMoneda');
        const modalForm = modalFormElement ? new bootstrap.Modal(modalFormElement) : null;

        const modalVerElement = document.getElementById('modalVerMoneda');
        const modalVer = modalVerElement ? new bootstrap.Modal(modalVerElement) : null;

        const $error = $('#monedaError');

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

            $('#codigo').val('');
            $('#nombre').val('');
            $('#simbolo').val('');

            /*
             * Las dos casillas se ponen a mano y no con reset(), porque
             * reset() las dejaria como estaban y al abrir el modal de alta
             * aparecerian marcadas las de la moneda que se estaba editando
             * antes. Entre un "editar el dolar" y un "nueva moneda" seguido,
             * apareceria una moneda nueva que salia ya como base y activa sin
             * que nadie lo hubiera pedido.
             */
            $('#es_moneda_base').prop('checked', false);
            $('#estado').prop('checked', true);

            $formulario.find('input[name="_method"]').remove();
        }

        /**
         * El mensaje del servidor, marcando el campo que fallo.
         *
         * Un error de validacion viene con un campo concreto. Se marca ese
         * campo, y no se suelta la lista en un bloque aparte, para que se vea
         * cual es sin tener que ir a buscarlo.
         *
         * Y hay un caso en el que el campo no vale para nada: el borrado de
         * una moneda en uso no falla en un campo, falla en la moneda entera.
         * El servidor lo manda con la clave "id", que no es un campo del
         * formulario, y por eso, cuando no se ha marcado ningun campo, el
         * mensaje se suelta entero en el bloque de arriba. Si no, el error
         * desaparecia: el bloque de arriba esta escondido y no hay ningun
         * "id" que marcar.
         */
        function mostrarError(xhr) {
            const respuesta = xhr.responseJSON || {};

            if (!respuesta.errors) {
                $error
                    .removeClass('d-none')
                    .html(respuesta.message || 'No se pudo guardar la moneda.');

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
                    .html(Object.values(respuesta.errors).flat().join('<br>'));
            }
        }

        // ------------------------------------------------------------------
        // Alta
        // ------------------------------------------------------------------

        $(document).on('click', '#btnNuevaMoneda', function () {
            limpiarError();
            limpiarFormulario();

            $('#modalMonedaLabel').text('Nueva moneda');
            $('#textoGuardarMoneda').text('Guardar moneda');

            $formulario.attr('action', $formulario.data('store-url'));

            modalForm?.show();

            /*
             * El foco se va al codigo y no al primer campo que trouve el
             * navegador. Es el que se teclea siempre primero y el que mas
             * veces se equivoca, asi que es donde interesa estar cuando se
             * abre el modal.
             */
            setTimeout(function () {
                $('#codigo').trigger('focus');
            }, 200);
        });

        // ------------------------------------------------------------------
        // Edicion
        // ------------------------------------------------------------------

        $(document).on('click', '.btn-edit-moneda', function (evento) {
            evento.preventDefault();

            abrirEdicion($(this).data('url'));
        });

        function abrirEdicion(url) {
            if (!url) {
                return;
            }

            $.ajax({
                url: url,
                method: 'GET',
                success: function (datos) {
                    limpiarError();
                    limpiarFormulario();

                    $('#modalMonedaLabel').text('Editar la moneda');
                    $('#textoGuardarMoneda').text('Guardar cambios');

                    /*
                     * El method de mentira es lo que hace que un formulario
                     * normal envie un PUT. Antes estaba puesto para el alta y
                     * se quita a proposito: si se dejara, el alta llegaria
                     * como un PUT a la ruta de crear.
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

                    $('#codigo').val(datos.codigo ?? '');
                    $('#nombre').val(datos.nombre ?? '');
                    $('#simbolo').val(datos.simbolo ?? '');
                    $('#es_moneda_base').prop('checked', !!datos.es_moneda_base);
                    $('#estado').prop('checked', !!datos.estado);

                    modalForm?.show();
                },
                error: function (xhr) {
                    mostrarError(xhr);
                    modalForm?.show();
                },
            });
        }

        // ------------------------------------------------------------------
        // Guardar
        // ------------------------------------------------------------------

        $formulario.on('submit', function (evento) {
            evento.preventDefault();

            limpiarError();

            const $boton = $('#btnGuardarMoneda');
            $boton.prop('disabled', true);

            /*
             * Las casillas sin marcar no viajan en el serialize(), porque no
             * tienen valor. El servidor las pone a false cuando no llegan, que
             * es lo que hace que desmarcar "es la base" se guarde de verdad.
             * Aqui no hay que hacer nada: no mandar nada ES mandar false.
             */
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
        // La ficha
        // ------------------------------------------------------------------

        $(document).on('click', '.btn-ver-moneda', function (evento) {
            evento.preventDefault();

            const url = $(this).data('url');

            if (!url) {
                return;
            }

            $('#verMonedaAviso').addClass('d-none').empty();

            $.ajax({
                url: url,
                method: 'GET',
                success: function (datos) {
                    $('#modalVerMonedaLabel').text(
                        datos.codigo + ' — ' + datos.nombre
                    );

                    $('#verMonedaCodigo').text(datos.codigo ?? '—');
                    $('#verMonedaNombre').text(datos.nombre ?? '—');
                    $('#verMonedaSimbolo').text(datos.simbolo ?? '—');

                    $('#verMonedaBase').html(
                        datos.es_moneda_base
                            ? '<span class="badge text-bg-primary">Base</span>'
                            : '<span class="text-muted">No</span>'
                    );

                    $('#verMonedaEstado').html(
                        datos.estado
                            ? '<span class="badge text-bg-success">Activa</span>'
                            : '<span class="badge text-bg-secondary">Inactiva</span>'
                    );

                    pintarUsos(datos.usos ?? []);

                    /*
                     * El boton de editar de la ficha guarda la url de la
                     * moneda que se esta viendo, para que al pulsarlo sepa a
                     * quien tiene que editar sin volver a preguntar al
                     * servidor. Es un dato que ya esta en la pagina y volver
                     * a pedirlo seria una llamada que no hace falta.
                     */
                    $('#btnEditarDesdeFicha').data('url', url.replace(/\/edit$/, ''));

                    modalVer?.show();
                },
                error: function (xhr) {
                    const respuesta = xhr.responseJSON || {};

                    $('#verMonedaAviso')
                        .removeClass('d-none')
                        .text(respuesta.message || 'No se pudo abrir la ficha.');

                    modalVer?.show();
                },
            });
        });

        function pintarUsos(usos) {
            const $usos = $('#verMonedaUsos').empty();
            const $aviso = $('#verMonedaSePuedeBorrar');

            $aviso.addClass('d-none').empty();

            if (usos.length === 0) {
                $usos.html(
                    '<p class="text-muted mb-0">No está en uso en ninguna parte. '
                    + 'Se puede eliminar.</p>'
                );

                $aviso
                    .removeClass('d-none alert-info')
                    .addClass('alert-success')
                    .html(
                        '<i class="bi bi-check-circle me-1"></i>'
                        + 'Esta moneda no se está usando en ningún sitio, así que se puede eliminar '
                        + 'sin dejar nada a medias.'
                    );

                return;
            }

            const $lista = $('<ul class="mb-0"></ul>');

            usos.forEach(function (uso) {
                $lista.append(
                    '<li class="d-flex justify-content-between border-bottom py-1">'
                    + '<span>' + uso.donde + '</span>'
                    + '<span class="font-monospace">' + uso.cuantas + '</span>'
                    + '</li>'
                );
            });

            $usos.append($lista);

            $aviso
                .removeClass('d-none alert-success')
                .addClass('alert-info')
                .html(
                    '<i class="bi bi-info-circle me-1"></i>'
                    + 'No se puede eliminar porque hay cosas guardadas con esta moneda. '
                    + 'Lo que se puede es desactivarla: deja de salir en los desplegables '
                    + 'y lo que ya se registró con ella se queda como está.'
                );
        }

        $(document).on('click', '#btnEditarDesdeFicha', function () {
            const url = $(this).data('url');

            modalVer?.hide();

            // El modal de edicion tarda un poco enmontarse al cerrar el otro
            setTimeout(function () {
                abrirEdicion(url ? url + '/edit' : null);
            }, 300);
        });

        // ------------------------------------------------------------------
        // Borrar
        // ------------------------------------------------------------------

        $(document).on('click', '[data-confirm-delete-moneda]', function (evento) {
            evento.preventDefault();

            const $boton = $(this);
            const url = $boton.data('url');
            const nombre = $boton.data('nombre') || '';

            if (!url) {
                return;
            }

            Swal.fire({
                title: '¿Eliminar la moneda ' + nombre + '?',
                html: 'Solo se puede eliminar si no está en ninguna parte. '
                    + 'Si hay algo guardado con ella, el servidor lo dirá y '
                    + 'podrás desactivarla en su lugar.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            }).then(function (resultado) {
                if (!resultado.isConfirmed) {
                    return;
                }

                $boton.prop('disabled', true);

                $.ajax({
                    url: url,
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
                    error: function (xhr) {
                        $boton.prop('disabled', false);

                        const respuesta = xhr.responseJSON || {};

                        const mensaje = respuesta.errors
                            ? Object.values(respuesta.errors).flat().join('<br>')
                            : (respuesta.message || 'No se pudo eliminar la moneda.');

                        Swal.fire({
                            title: 'No se puede eliminar',
                            html: mensaje,
                            icon: 'warning',
                            confirmButtonText: 'Entendido',
                        });
                    },
                });
            });
        });

        function recargarTabla() {
            if ($.fn.dataTable.isDataTable('#monedas-table')) {
                $('#monedas-table').DataTable().ajax.reload();
            } else {
                window.location.reload();
            }
        }
    });
})(jQuery);
