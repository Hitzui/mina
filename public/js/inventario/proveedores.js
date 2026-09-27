/**
 * Proveedores: alta, edicion y ficha, los tres en modal.
 *
 * Es el mismo camino que siguen los materiales, con una diferencia: el
 * formulario de un proveedor no lleva cantidades ni saldos, asi que cabe de
 * sobra en un modal y no hay que inventar una pagina aparte.
 *
 * Todo se rellena desde el servidor: los botones de la fila llevan su url en
 * un data-url, y el javascript pide los datos y los pone. No se copia nada
 * del html de la fila a mano, que es como se desincroniza una tabla con su
 * detalle.
 *
 * Lo que el usuario escribe llega por json y se pinta como html, asi que se
 * escapa antes de meterlo. Sin eso, un proveedor llamado con un script en el
 * nombre lo ejecutaria en la pagina de quien lo mirara.
 */
(function ($) {
    'use strict';

    $(function () {
        const $formulario = $('#formProveedor');

        if (!$formulario.length) {
            return;
        }

        const modalForm = document.getElementById('modalProveedor');
        const modal = modalForm ? new bootstrap.Modal(modalForm) : null;

        const modalShowElement = document.getElementById('modalShowProveedor');
        const modalShow = modalShowElement ? new bootstrap.Modal(modalShowElement) : null;

        const $error = $('#proveedorError');
        const $aviso = $('#showAvisoContenedor');

        // ------------------------------------------------------------------
        // Utilidades
        // ------------------------------------------------------------------

        function limpiarError() {
            $error.addClass('d-none').empty();
        }

        function limpiarFormulario() {
            $formulario[0].reset();

            // El 0 escondido del estado vuelve a 0 y el interruptor se marca
            $formulario.find('input[name="estado"]').first().val('0');
            $('#estado').prop('checked', true);

            $formulario.find('.is-invalid').removeClass('is-invalid');
            $formulario.find('.invalid-feedback').hide().text('');
            $aviso.empty();
        }

        /**
         * El mensaje del servidor, marcando el campo que fallo.
         *
         * Un error de validacion viene con un campo concreto. Se marca ese
         * campo, y no se suelta la lista en un bloque aparte, para que se vea
         * cual es sin tener que ir a buscarlo.
         */
        function mostrarError(xhr) {
            const respuesta = xhr.responseJSON || {};

            if (!respuesta.errors) {
                $error
                    .removeClass('d-none')
                    .html(respuesta.message || 'No se pudo guardar el proveedor.');

                return;
            }

            let marcados = 0;

            $formulario.find('.is-invalid').removeClass('is-invalid');
            $formulario.find('.invalid-feedback').hide().text('');

            Object.entries(respuesta.errors).forEach(function (par) {
                const campo = par[0];
                const mensajes = par[1];

                $formulario.find('[name="' + campo + '"]').addClass('is-invalid');

                $formulario
                    .find('[name="' + campo + '"]')
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

        /**
         * Escapa lo que se pinta con html.
         */
        function escapar(valor) {
            return String(valor ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // ------------------------------------------------------------------
        // Alta
        // ------------------------------------------------------------------

        $(document).on('click', '#btnNuevoProveedor', function () {
            limpiarFormulario();

            $('#modalProveedorLabel').text('Nuevo proveedor');
            $('#textoGuardarProveedor').text('Crear proveedor');

            // Sin method escondido: es un alta
            $formulario.find('input[name="_method"]').remove();
            $formulario.attr('action', $formulario.data('store-url'));

            modal?.show();

            // El foco va al nombre: el codigo ya no se escribe
            $('#nombre').trigger('focus');
        });

        // ------------------------------------------------------------------
        // Edicion
        // ------------------------------------------------------------------

        $(document).on('click', '.btn-edit-proveedor', function (evento) {
            evento.preventDefault();

            const url = $(this).data('url');

            if (!url) {
                return;
            }

            $.ajax({
                url: url,
                method: 'GET',
                success: function (datos) {
                    limpiarFormulario();

                    $('#modalProveedorLabel').text('Editar proveedor');
                    $('#textoGuardarProveedor').text('Guardar cambios');

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

                    // El codigo no se manda: lo pone el servidor
                    $('#nombre').val(datos.nombre ?? '');
                    $('#contacto').val(datos.contacto ?? '');
                    $('#telefono').val(datos.telefono ?? '');
                    $('#email').val(datos.email ?? '');
                    $('#direccion').val(datos.direccion ?? '');
                    $('#observaciones').val(datos.observaciones ?? '');
                    $('#estado').prop('checked', !!datos.estado);

                    modal?.show();
                },
                error: function (xhr) {
                    mostrarError(xhr);
                    modal?.show();
                },
            });
        });

        // ------------------------------------------------------------------
        // Ficha
        // ------------------------------------------------------------------

        $(document).on('click', '.btn-show-proveedor', function (evento) {
            evento.preventDefault();

            const url = $(this).data('url');

            if (!url) {
                return;
            }

            // Se vacia antes de pedir, para que al abrirse nunca se vean los
            // datos del proveedor que se estaba viendo antes
            $aviso.empty();

            $.ajax({
                url: url,
                method: 'GET',
                success: function (datos) {
                    $('#showNombre').text(datos.nombre ?? '—');
                    $('#showCodigo').text(datos.codigo ?? '—');
                    $('#showContacto').text(datos.contacto || '—');
                    $('#showTelefono').text(datos.telefono || '—');
                    $('#showEmail').text(datos.email || '—');
                    $('#showDireccion').text(datos.direccion || '—');
                    $('#showObservaciones').text(datos.observaciones || '—');

                    $('#showEstado').html(
                        datos.estado
                            ? '<span class="badge bg-success">Activo</span>'
                            : '<span class="badge bg-secondary">Inactivo</span>'
                    );

                    $('#showCompras').text(
                        datos.compras === 0
                            ? 'Ninguna todavía'
                            : datos.compras + (datos.compras === 1 ? ' compra' : ' compras')
                    );

                    /*
                     * Con compras registradas, borrarlo dejaria el historial
                     * de lo que se compro sin origen. Se avisa antes de que
                     * se pulse borrar, no despues de hacerlo.
                     */
                    if (datos.tiene_compras) {
                        $aviso.html(
                            '<div class="alert alert-warning mb-0">'
                            + '<i class="bi bi-info-circle me-1"></i>'
                            + 'Tiene <strong>' + datos.compras + '</strong> compra(s) registradas. '
                            + 'Si lo elimina, se desactivará en vez de borrarse, para que esas '
                            + 'compras sigan teniendo un origen.'
                            + '</div>'
                        );
                    }

                    modalShow?.show();
                },
                error: function (xhr) {
                    const respuesta = xhr.responseJSON || {};

                    $('#showNombre').text(respuesta.message || 'No se pudo obtener la ficha.');
                    modalShow?.show();
                },
            });
        });

        // ------------------------------------------------------------------
        // Guardar
        // ------------------------------------------------------------------

        $formulario.on('submit', function (evento) {
            evento.preventDefault();

            limpiarError();

            const $boton = $('#btnGuardarProveedor');
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

                    modal?.hide();

                    // Se recarga la tabla para que la columna de compras
                    // salga ya contada
                    if ($.fn.dataTable.isDataTable('#proveedores-table')) {
                        $('#proveedores-table').DataTable().ajax.reload();
                    } else {
                        window.location.reload();
                    }
                },
                error: function (xhr) {
                    $boton.prop('disabled', false);
                    mostrarError(xhr);
                },
            });
        });
    });
})(jQuery);
