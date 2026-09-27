/**
 * Materiales: alta, edicion y ficha, los tres en modal.
 *
 * El formulario es el mismo para crear y para editar, y la ficha es un
 * modal aparte. Todo se rellena desde el servidor: los botones de la fila
 * llevan su url en un data-url, y el javascript pide los datos y los pone.
 * No se copia nada del html de la fila a mano, que es como se desincroniza
 * una tabla con el detalle.
 *
 * Los textos van con acentos: el resto de la aplicacion los trae en UTF-8 y
 * el navegador los muestra bien, asi que no hay motivo para esquivarlos.
 */
(function ($) {
    'use strict';

    $(function () {
        const $formulario = $('#formProducto');

        if (!$formulario.length) {
            return;
        }

        const modalForm = document.getElementById('modalProducto');
        const modal = modalForm ? new bootstrap.Modal(modalForm) : null;

        const modalShowElement = document.getElementById('modalShowProducto');
        const modalShow = modalShowElement ? new bootstrap.Modal(modalShowElement) : null;

        const $error = $('#productoError');
        const $tablaMovimientos = $('#tablaMovimientosProducto tbody');

        // ------------------------------------------------------------------
        // Utilidades
        // ------------------------------------------------------------------

        function numero(valor, decimales) {
            const n = parseFloat(valor);

            return isNaN(n)
                ? '—'
                : n.toLocaleString('es-NI', {
                    minimumFractionDigits: decimales,
                    maximumFractionDigits: decimales,
                });
        }

        function limpiarError() {
            $error.addClass('d-none').empty();
        }

        function limpiarAvisos() {
            $formulario.find('.is-invalid').removeClass('is-invalid');
            $formulario.find('.invalid-feedback').hide().text('');
            $('#showAvisoContenedor').empty();
        }

        /**
         * Vacia el formulario para un alta nueva.
         */
        function limpiarFormulario() {
            $formulario[0].reset();

            // El 0 escondido del estado vuelve a 0, que es lo que quiere un
            // material nuevo, y el interruptor vuelve a marcado
            $formulario.find('input[name="estado"]').first().val('0');
            $('#estado').prop('checked', true);

            limpiarAvisos();
        }

        /**
         * El mensaje del servidor, tal cual.
         *
         * Un error de validacion viene con un campo concreto. Se marca ese
         * campo para que se vea cual es, en vez de soltar la lista de
         * mensajes en un bloque sin relacion con el formulario.
         */
        function mostrarError(xhr) {
            const respuesta = xhr.responseJSON || {};

            if (!respuesta.errors) {
                $error
                    .removeClass('d-none')
                    .html(respuesta.message || 'No se pudo guardar el material.');

                return;
            }

            let primero = true;

            $formulario.find('.is-invalid').removeClass('is-invalid');
            $formulario.find('.invalid-feedback').hide().text('');

            Object.entries(respuesta.errors).forEach(function (par) {
                const campo = par[0];
                const mensajes = par[1];

                $formulario
                    .find('[name="' + campo + '"]')
                    .addClass('is-invalid');

                $formulario
                    .find('[name="' + campo + '"]')
                    .siblings('.invalid-feedback')
                    .first()
                    .text(Array.isArray(mensajes) ? mensajes[0] : mensajes)
                    .show();

                primero = false;
            });

            if (primero) {
                $error
                    .removeClass('d-none')
                    .html(Object.values(respuesta.errors).flat().join('<br>'));
            }
        }

        // ------------------------------------------------------------------
        // Alta
        // ------------------------------------------------------------------

        $(document).on('click', '#btnNuevoProducto', function () {
            limpiarFormulario();

            $('#modalProductoLabel').text('Nuevo material');
            $('#textoGuardarProducto').text('Crear material');

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

        $(document).on('click', '.btn-edit-producto', function (evento) {
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

                    $('#modalProductoLabel').text('Editar material');
                    $('#textoGuardarProducto').text('Guardar cambios');

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

                    // El codigo no se manda: lo pone el servidor. El campo
                    // del formulario es de solo lectura y solo sirve para
                    // mostrarlo, asi que no se toca aqui.
                    $('#nombre').val(datos.nombre ?? '');
                    $('#unidad_medida').val(datos.unidad_medida ?? '');
                    $('#categoria').val(datos.categoria ?? '');
                    $('#stock_minimo').val(datos.stock_minimo ?? 0);
                    $('#descripcion').val(datos.descripcion ?? '');
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

        $(document).on('click', '.btn-show-producto', function (evento) {
            evento.preventDefault();

            const url = $(this).data('url');

            if (!url) {
                return;
            }

            // Se vacia antes de pedir, para que al abrirse nunca se vean los
            // datos del material que se estaba viendo antes
            limpiarAvisos();
            $tablaMovimientos.html(
                '<tr><td colspan="5" class="text-center text-muted py-3">Cargando...</td></tr>'
            );

            $.ajax({
                url: url,
                method: 'GET',
                success: function (datos) {
                    $('#showNombre').text(datos.nombre ?? '—');
                    $('#showCodigo').text(datos.codigo ?? '—');
                    $('#showUnidad').text(datos.unidad_medida ?? '—');
                    $('#showCategoria').text(datos.categoria || '—');
                    $('#showDescripcion').text(datos.descripcion || '—');

                    $('#showEstado').html(
                        datos.estado
                            ? '<span class="badge bg-success">Activo</span>'
                            : '<span class="badge bg-secondary">Inactivo</span>'
                    );

                    const unidad = datos.unidad_medida ?? '';

                    $('#showExistencia')
                        .text((datos.existencia ?? '0') + ' ' + unidad)
                        .toggleClass('text-muted', parseFloat(datos.existencia) <= 0);

                    /*
                     * Sin costo cargado, cualquier consumo de este material
                     * sumaria cero al costo del proceso sin avisar. Se dice
                     * aqui porque es el momento en que se puede arreglar:
                     * registrando una entrada con su precio.
                     */
                    $('#showCostoPromedio').html(
                        parseFloat(datos.costo_promedio) > 0
                            ? numero(datos.costo_promedio, 4)
                            : '<span class="text-muted">sin costo</span>'
                    );

                    $('#showValor').text(numero(datos.valor_inventario, 2));
                    $('#showMinimo').text(numero(datos.stock_minimo, 0) + ' ' + unidad);

                    // Los avisos
                    let avisos = '';

                    if (parseFloat(datos.costo_promedio) <= 0) {
                        avisos +=
                            '<div class="alert alert-warning mb-2">'
                            + '<i class="bi bi-exclamation-triangle me-1"></i>'
                            + 'Este material no tiene costo cargado. Mientras no se registre '
                            + 'una entrada con su precio, cualquier consumo suyo sumará '
                            + '<strong>cero</strong> al costo del proceso.'
                            + '</div>';
                    }

                    if (datos.por_debajo_del_minimo) {
                        avisos +=
                            '<div class="alert alert-danger mb-0">'
                            + '<i class="bi bi-exclamation-triangle me-1"></i>'
                            + 'Está por debajo del mínimo: conviene reponer.'
                            + '</div>';
                    }

                    $('#showAvisoContenedor').html(avisos);

                    pintarMovimientos(datos.movimientos || []);

                    modalShow?.show();
                },
                error: function (xhr) {
                    $tablaMovimientos.html(
                        '<tr><td colspan="5" class="text-center text-danger py-3">'
                        + 'No se pudo obtener la ficha del material.</td></tr>'
                    );
                    modalShow?.show();
                },
            });
        });

        /**
         * Los movimientos del kardex dentro del modal.
         */
        function pintarMovimientos(movimientos) {
            if (movimientos.length === 0) {
                $tablaMovimientos.html(
                    '<tr><td colspan="5" class="text-center text-muted py-3">'
                    + 'Este material todavía no tiene movimientos en el almacén.'
                    + '</td></tr>'
                );

                return;
            }

            let html = '';

            movimientos.forEach(function (movimiento) {
                const clase = movimiento.es_entrada ? 'bg-success' : 'bg-warning';

                html +=
                    '<tr>'
                    + '<td>' + escapar(movimiento.fecha) + '</td>'
                    + '<td><span class="badge ' + clase + '">'
                    + escapar(movimiento.tipo) + '</span></td>'
                    + '<td class="text-end fw-semibold">'
                    + escapar(movimiento.cantidad) + '</td>'
                    + '<td class="text-end">' + escapar(movimiento.costo_total) + '</td>'
                    + '<td class="small">' + escapar(movimiento.destino) + '</td>'
                    + '</tr>';
            });

            $tablaMovimientos.html(html);
        }

        /**
         * Escapa lo que se pinta con html.
         *
         * El nombre de un material y su descripcion los escribe el usuario
         * y llegan por json. Se insertan como html por rendimiento, asi que
         * sin esto un material llamado "&lt;script&gt;" ejecutaria su propio
         * script en la pagina de quien lo mire.
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
        // Guardar
        // ------------------------------------------------------------------

        $formulario.on('submit', function (evento) {
            evento.preventDefault();

            limpiarError();

            const $boton = $('#btnGuardarProducto');
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

                    // Se recarga la tabla para que la existencia, el costo
                    // promedio y el valor salgan ya calculados
                    if ($.fn.dataTable.isDataTable('#productos-table')) {
                        $('#productos-table').DataTable().ajax.reload();
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
