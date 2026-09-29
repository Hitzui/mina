/**
 * Precio del oro: alta, edicion, borrado e importacion del mes.
 *
 * Es el mismo camino que el tipo de cambio —alta y edicion en modal sobre la
 * lista, importacion en dos pasos— porque las dos series se cargan igual. La
 * diferencia que si importa va en un sitio y no en otro:
 *
 * El cero. En el tipo de cambio el cero no se admite, y el boton de guardar
 * manda el valor tal cual. Aqui el cero si se admite y significa "de este dia
 * no se sabe el precio", asi que el formulario avisa de lo que significa, la
 * lista lo enseña tachado, y el servidor se salta los ceros al buscar el precio
 * de un dia. Nada de eso cambia lo que hace este archivo: lo unico que hace es
 * no dejar que el usuario se crea que un cero es una cotizacion de cero.
 *
 * El _token va en el cuerpo del formulario y no en la cabecera. El layout de
 * este proyecto no trae <meta name="csrf-token">, asi que el jquery lo leeria
 * como undefined en cualquier sitio, y un FormData armado a mano no lleva el
 * token arrastrado por serialize(). Por eso se saca del propio formulario, que
 * si lo tiene.
 */
(function ($) {
    'use strict';

    $(function () {
        const $formulario = $('#formPrecioOro');

        if (!$formulario.length) {
            return;
        }

        const modalFormElement = document.getElementById('modalPrecioOro');
        const modalForm = modalFormElement ? new bootstrap.Modal(modalFormElement) : null;

        const $error = $('#precioOroError');

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

            $('#fecha').val('');
            $('#precio').val('');
            $('#fuente').val('');
            $('#observaciones').val('');

            $('#unidad').val('gramo');

            /*
             * El desplegable de la moneda se limpia a mano y no con reset(),
             * porque el reset no sabe lo que hay dentro de un select2: deja
             * escrito en pantalla el valor anterior mientras el campo real ya
             * esta vacio. Al abrir el modal de nuevo se veria "Dolares" y al
             * guardar se mandaria el id del Dolares con la fecha de hoy.
             */
            if ($.fn.select2 && $('#moneda_id').data('select2')) {
                $('#moneda_id').val(null).trigger('change');
            } else {
                $('#moneda_id').val('');
            }

            $formulario.find('input[name="_method"]').remove();
        }

        function mostrarError(xhr) {
            const respuesta = xhr.responseJSON || {};

            if (!respuesta.errors) {
                $error
                    .removeClass('d-none')
                    .html(respuesta.message || 'No se pudo guardar el precio.');

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

        // ------------------------------------------------------------------
        // Alta
        // ------------------------------------------------------------------

        $(document).on('click', '#btnNuevoPrecioOro', function () {
            limpiarError();
            limpiarFormulario();

            $('#modalPrecioOroLabel').text('Nuevo precio del oro');
            $('#textoGuardarPrecioOro').text('Guardar precio');

            $formulario.attr('action', $formulario.data('store-url'));

            modalForm?.show();
        });

        // ------------------------------------------------------------------
        // Edicion
        // ------------------------------------------------------------------

        $(document).on('click', '.btn-edit-precio-oro', function (evento) {
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

                    $('#modalPrecioOroLabel').text('Editar el precio del oro');
                    $('#textoGuardarPrecioOro').text('Guardar cambios');

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

                    $('#fecha').val(datos.fecha ?? '');
                    $('#precio').val(datos.precio ?? '');
                    $('#unidad').val(datos.unidad ?? 'gramo');
                    $('#fuente').val(datos.fuente ?? '');
                    $('#observaciones').val(datos.observaciones ?? '');

                    if ($.fn.select2 && $('#moneda_id').data('select2')) {
                        $('#moneda_id').val(datos.moneda_id ?? '').trigger('change');
                    } else {
                        $('#moneda_id').val(datos.moneda_id ?? '');
                    }

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

            const $boton = $('#btnGuardarPrecioOro');
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

        $(document).on('submit', 'form[data-confirm-delete-precio-oro]', function (evento) {
            evento.preventDefault();

            const $formularioBorrar = $(this);
            const fecha = $formularioBorrar.data('fecha') || '';

            /*
             * El aviso dice lo que pasa con el dia que queda sin precio, que
             * no es que se quede sin valor: al valorar se usara el ultimo
             * precio que se sepa. Sin esa frase, el aviso asusta de mas y
             * alguien que solo quiere quitar un precio mal cargado no lo
             * borraria.
             */
            Swal.fire({
                title: '¿Eliminar el precio del ' + fecha + '?',
                html: 'Ese día se queda sin precio guardado. Las valoraciones de '
                    + 'ese día usarán el último precio que se sepa, que no es cero.',
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
            if ($.fn.dataTable.isDataTable('#precios-oro-table')) {
                $('#precios-oro-table').DataTable().ajax.reload();
            } else {
                window.location.reload();
            }
        }

        // ==================================================================
        // La importacion del mes
        // ==================================================================

        const modalImportarElement = document.getElementById('modalImportarPrecioOro');
        const modalImportar = modalImportarElement ? new bootstrap.Modal(modalImportarElement) : null;

        const $formularioImportar = $('#formImportarPrecioOro');
        const $errorImportar = $('#importarError');
        const $resumen = $('#importarResumen');

        /*
         * El archivo que se ha leido, guardado para mandarlo otra vez al
         * confirmar. Se guarda en memoria y no se vuelve a pedir al usuario:
         * el boton de confirmar tiene que importar EXACTAMENTE lo que se le
         * teacho en la vista previa. Si al confirmar se le pidiera el archivo
         * otra vez, entre una pantalla y otra podria cambiar el archivo en el
         * disco y se importaria algo que el usuario no ha visto nunca.
         */
        let archivoElegido = null;

        function limpiarErrorImportar() {
            $errorImportar.addClass('d-none').empty();
        }

        function mostrarErrorImportar(mensaje) {
            $errorImportar
                .removeClass('d-none')
                .html('<i class="bi bi-exclamation-triangle me-1"></i>' + mensaje);
        }

        function prepararImportacion() {
            archivoElegido = null;

            $formularioImportar[0].reset();

            $('#unidadImportar').val('gramo');

            if ($.fn.select2 && $('#monedaImportar').data('select2')) {
                $('#monedaImportar').val(null).trigger('change');
            } else {
                $('#monedaImportar').val('');
            }

            $resumen.addClass('d-none');
            $('#btnImportarPrecioOro').addClass('d-none');
            $('#btnVerPrecioOro').removeClass('d-none');
        }

        $(document).on('click', '#btnImportarPrecioOroAbrir', function () {
            limpiarErrorImportar();
            prepararImportacion();

            modalImportar?.show();
        });

        /**
         * El primer paso: leer el archivo y contarlo. No escribe nada.
         */
        $(document).on('click', '#btnVerPrecioOro', function () {
            limpiarErrorImportar();

            const archivo = $('#archivoPrecioOro')[0]?.files?.[0];
            const moneda = $('#monedaImportar').val();
            const unidad = $('#unidadImportar').val();

            if (!archivo) {
                mostrarErrorImportar('Elija el archivo de Excel.');

                return;
            }

            if (!unidad) {
                mostrarErrorImportar('Elija de qué unidad es el precio.');

                return;
            }

            if (!moneda) {
                mostrarErrorImportar('Elija de qué moneda es el precio.');

                return;
            }

            archivoElegido = archivo;

            const $boton = $(this);
            $boton.prop('disabled', true);

            /*
             * El token se saca del propio formulario, y no de un meta. El
             * layout de este proyecto no trae <meta name="csrf-token">, asi que
             * el jquery lo leeria como undefined, y un FormData armado a mano
             * —que es lo que se necesita para subir un archivo— no lleva el
             * token arrastrado por serialize(). Sin esto, la peticion llega
             * sin token y el servidor contesta un 419 sin decir nada.
             */
            const datosImportar = new FormData();

            datosImportar.append('archivo', archivo);
            datosImportar.append('moneda_id', moneda);
            datosImportar.append('unidad', unidad);
            datosImportar.append('fuente', $('#fuenteImportar').val() || '');
            datosImportar.append(
                '_token',
                $('#formImportarPrecioOro input[name="_token"]').val()
            );

            $.ajax({
                url: $formularioImportar.attr('action'),
                method: 'POST',
                data: datosImportar,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                success: function (respuesta) {
                    $boton.prop('disabled', false);

                    pintarVistaPrevia(respuesta);

                    $('#btnImportarPrecioOro').removeClass('d-none');
                },
                error: function (xhr) {
                    $boton.prop('disabled', false);

                    const respuesta = xhr.responseJSON || {};

                    const mensaje = respuesta.errors
                        ? Object.values(respuesta.errors).flat().join('<br>')
                        : (respuesta.message || 'No se pudo leer el archivo.');

                    mostrarErrorImportar(mensaje);
                },
            });
        });

        function pintarVistaPrevia(datos) {
            const partes = [];

            partes.push(
                'El archivo trae <strong>' + datos.total + '</strong> día(s), del '
                + datos.desde.split('-').reverse().join('/') + ' al '
                + datos.hasta.split('-').reverse().join('/') + '.'
            );

            partes.push(
                '<strong>' + datos.nuevos + '</strong> nuevos y '
                + datos.cambian.length + ' que cambian de precio.'
            );

            if (datos.sin_cambiar > 0) {
                partes.push(datos.sin_cambiar + ' ya estaban y se quedan como están.');
            }

            if (datos.recuperados > 0) {
                partes.push(
                    '<span class="text-warning-emphasis">'
                    + datos.recuperados
                    + ' estaban borrados y se recuperan.</span>'
                );
            }

            if (datos.descartadas.length > 0) {
                partes.push(
                    '<span class="text-warning-emphasis">'
                    + datos.descartadas.length
                    + ' fila(s) del archivo no se van a importar.</span>'
                );
            }

            $('#importarAviso').html(partes.join(' '));

            const $filas = $('#importarFilas').empty();

            if (datos.cambian.length === 0) {
                $filas.append(
                    '<tr><td colspan="3" class="text-muted text-center py-3">'
                    + 'Ningún día cambia de precio: lo que trae el archivo es lo que ya hay.</td></tr>'
                );
            } else {
                datos.cambian.forEach(function (cambio) {
                    const $fila = $('<tr>');

                    $fila.append(
                        '<td class="font-monospace">'
                        + cambio.fecha.split('-').reverse().join('/')
                        + (cambio.borrado
                            ? ' <span class="badge text-bg-warning">borrado</span>'
                            : '')
                        + '</td>'
                    );

                    $fila.append(
                        '<td class="text-end font-monospace text-danger">'
                        + Number(cambio.antes).toFixed(4)
                        + '</td>'
                    );

                    $fila.append(
                        '<td class="text-end font-monospace text-success">'
                        + Number(cambio.despues).toFixed(4)
                        + '</td>'
                    );

                    $filas.append($fila);
                });
            }

            pintarZeros(datos.sin_cero_que_pisando ?? []);

            if (datos.descartadas.length > 0) {
                const $detalle = $('#importarDescartadas').empty();

                const $lista = $('<ul class="mb-0 small"></ul>');

                datos.descartadas.forEach(function (una) {
                    $lista.append(
                        '<li>Fila ' + una.fila + ': ' + una.motivo + '</li>'
                    );
                });

                $detalle.append(
                    '<h6 class="mt-3 mb-1">Filas del archivo que no se importan</h6>'
                ).append($lista);
            } else {
                $('#importarDescartadas').empty();
            }
        }

        /**
         * Los dias de los que el archivo trae un cero y ya habia un precio.
         *
         * Se pintan aparte y no en la tabla de cambios porque el cambio no va
         * a ocurrir: el dia se queda con el precio que tenia. Si se mezclaran
         * con los cambios de verdad, el usuario veria "de 78.45 a 0" y no
         * sabria si el precio se ha puesto en cero o si el archivo venia con
         * la celda vacia.
         */
        function pintarZeros(ignorar) {
            const $detalle = $('#importarZeros').empty();

            if (ignorar.length === 0) {
                return;
            }

            const dias = [];

            ignorar.forEach(function (uno) {
                dias.push(
                    uno.fecha.split('-').reverse().join('/')
                    + ' (se queda en ' + Number(uno.antes).toFixed(4) + ')'
                );
            });

            $detalle.append(
                '<div class="alert alert-warning mt-3 mb-0">'
                + '<i class="bi bi-exclamation-triangle me-1"></i>'
                + '<strong>' + ignorar.length + '</strong> día(s) traen cero en el '
                + 'archivo y ya tenían un precio. El cero no va a pisar el precio '
                + 'que había: se quedan como están. Son estos: '
                + '<span class="font-monospace">' + dias.join(', ') + '</span>'
                + '</div>'
            );
        }

        /**
         * El segundo paso: importar exactamente lo que se le teacho.
         */
        $(document).on('click', '#btnImportarPrecioOro', function () {
            limpiarErrorImportar();

            if (!archivoElegido) {
                mostrarErrorImportar('Primero tiene que mirar qué trae el archivo.');

                return;
            }

            const $boton = $(this);
            $boton.prop('disabled', true);

            const datosImportar = new FormData();

            datosImportar.append('archivo', archivoElegido);
            datosImportar.append('moneda_id', $('#monedaImportar').val());
            datosImportar.append('unidad', $('#unidadImportar').val());
            datosImportar.append('fuente', $('#fuenteImportar').val() || '');
            datosImportar.append('confirmar', 1);
            datosImportar.append(
                '_token',
                $('#formImportarPrecioOro input[name="_token"]').val()
            );

            $.ajax({
                url: $formularioImportar.attr('action'),
                method: 'POST',
                data: datosImportar,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                success: function (respuesta) {
                    $boton.prop('disabled', false);

                    let html = 'Se han guardado <strong>' + respuesta.guardados + '</strong> día(s).'
                        + '<br>'
                        + respuesta.nuevos + ' nuevos y ' + respuesta.cambiados + ' corregidos.';

                    if (respuesta.recuperados > 0) {
                        html += '<br>' + respuesta.recuperados
                            + ' recuperados de días que estaban borrados.';
                    }

                    if (respuesta.zeros_ignorados > 0) {
                        html += '<br>' + respuesta.zeros_ignorados
                            + ' con cero en el archivo que no han pisado el precio que había.';
                    }

                    Swal.fire({
                        title: 'Mes importado',
                        html: html,
                        icon: 'success',
                        confirmButtonText: 'Entendido',
                    }).then(function () {
                        modalImportar?.hide();

                        recargarTabla();
                    });
                },
                error: function (xhr) {
                    $boton.prop('disabled', false);

                    const respuesta = xhr.responseJSON || {};

                    const mensaje = respuesta.errors
                        ? Object.values(respuesta.errors).flat().join('<br>')
                        : (respuesta.message || 'No se pudo importar el mes.');

                    mostrarErrorImportar(mensaje);
                },
            });
        });
    });
})(jQuery);
