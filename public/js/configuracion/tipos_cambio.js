/**
 * Tipo de cambio: alta, edicion, borrado e importacion del mes.
 *
 * Sigue el mismo camino que los proveedores y los materiales: los tres en
 * modal sobre la lista. La diferencia que si importa es la importacion, que
 * va en dos pasos, y esa esta explicada en su sitio.
 *
 * Todo se rellena desde el servidor: los botones de la fila llevan su url en
 * un data-url, y el javascript pide los datos y los pone. No se copia nada
 * del html de la fila a mano, que es como se desincroniza una tabla con su
 * detalle.
 */
(function ($) {
    'use strict';

    $(function () {
        const $formulario = $('#formTipoCambio');

        if (!$formulario.length) {
            return;
        }

        const modalFormElement = document.getElementById('modalTipoCambio');
        const modalForm = modalFormElement ? new bootstrap.Modal(modalFormElement) : null;

        const $error = $('#tipoCambioError');

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

            /*
             * El desplegable de la moneda se limpia a mano y no con reset(),
             * porque el reset no sabe lo que hay dentro de un select2: deja
             * escrito en pantalla el valor anterior mientras el campo real
             * ya esta vacio. Al abrir el modal de nuevo se veria "Dolares"
             * y al guardar se mandaria el id del Dolares con la fecha de hoy,
             * que es justo el tipo de compra que sale mal sin avisar.
             */
            if ($.fn.select2 && $('#moneda_id').data('select2')) {
                $('#moneda_id').val(null).trigger('change');
            } else {
                $('#moneda_id').val('');
            }

            $formulario.find('input[name="_method"]').remove();
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
                    .html(respuesta.message || 'No se pudo guardar el tipo de cambio.');

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

        $(document).on('click', '#btnNuevoTipoCambio', function () {
            limpiarError();
            limpiarFormulario();

            $('#modalTipoCambioLabel').text('Nuevo tipo de cambio');
            $('#textoGuardarTipoCambio').text('Guardar tipo de cambio');

            $formulario.attr('action', $formulario.data('store-url'));

            modalForm?.show();
        });

        // ------------------------------------------------------------------
        // Edicion
        // ------------------------------------------------------------------

        $(document).on('click', '.btn-edit-tipo-cambio', function (evento) {
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

                    $('#modalTipoCambioLabel').text('Editar el tipo de cambio');
                    $('#textoGuardarTipoCambio').text('Guardar cambios');

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

                    $('#fecha').val(datos.fecha ?? '');
                    $('#valor').val(datos.valor ?? '');
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

            const $boton = $('#btnGuardarTipoCambio');
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

        $(document).on('submit', 'form[data-confirm-delete-tipo-cambio]', function (evento) {
            evento.preventDefault();

            const $formularioBorrar = $(this);
            const fecha = $formularioBorrar.data('fecha') || '';

            /*
             * El aviso lo pone SweetAlert, que ya esta en la pagina, y no el
             * confirm() del navegador. El del navegador sale con un boton de
             * Aceptar y otro de Cancelar sin decir nada, y aqui hay que decir
             * de que dia se trata: se puede estar borrando el dia equivocado
             * porque la lista esta ordenada al reves y el de abajo es el de
             * mas abajo de todo, no el de ayer.
             */
            Swal.fire({
                title: '¿Eliminar el tipo de cambio del ' + fecha + '?',
                html: 'Las compras y el material de ese día se quedarán sin '
                    + 'equivalente en córdoba hasta que se ponga otro valor.',
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
            if ($.fn.dataTable.isDataTable('#tipos-cambio-table')) {
                $('#tipos-cambio-table').DataTable().ajax.reload();
            } else {
                window.location.reload();
            }
        }

        // ==================================================================
        // La importacion del mes
        // ==================================================================

        const modalImportarElement = document.getElementById('modalImportarTipoCambio');
        const modalImportar = modalImportarElement ? new bootstrap.Modal(modalImportarElement) : null;

        const $formularioImportar = $('#formImportarTipoCambio');
        const $errorImportar = $('#importarError');
        const $resumen = $('#importarResumen');

        /*
         * El archivo que se ha leido, guardado para mandarlo otra vez al
         * confirmar. Se guarda en memoria y no se vuelve a pedir al usuario:
         * el boton de confirmar tiene que importar EXACTAMENTE lo que se le
         * teacho en la vista previa. Si al confirmar se le pidiera el
         * archivo otra vez, entre una pantalla y otra podria cambiar el
         * archivo en el disco y se importaria algo que el usuario no ha
         * visto nunca.
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

            if ($.fn.select2 && $('#monedaImportar').data('select2')) {
                $('#monedaImportar').val(null).trigger('change');
            } else {
                $('#monedaImportar').val('');
            }

            $resumen.addClass('d-none');
            $('#btnImportarTipoCambio').addClass('d-none');
            $('#btnVerTipoCambio').removeClass('d-none');
        }

        $(document).on('click', '#btnImportarTipoCambioAbrir', function () {
            limpiarErrorImportar();
            prepararImportacion();

            modalImportar?.show();
        });

        /**
         * El primer paso: leer el archivo y contarlo.
         *
         * No escribe nada. Es lo que separa "he mirado lo que trae" de "lo he
         * metido", que con treinta dias de conversiones no es lo mismo.
         */
        $(document).on('click', '#btnVerTipoCambio', function () {
            limpiarErrorImportar();

            const archivo = $('#archivoTipoCambio')[0]?.files?.[0];
            const moneda = $('#monedaImportar').val();

            if (!archivo) {
                mostrarErrorImportar('Elija el archivo de Excel.');

                return;
            }

            if (!moneda) {
                mostrarErrorImportar('Elija de qué moneda es este tipo de cambio.');

                return;
            }

            archivoElegido = archivo;

            const $boton = $(this);
            $boton.prop('disabled', true);

            enviarFormulario(false, function (respuesta) {
                $boton.prop('disabled', false);

                if (respuesta.vista_previa) {
                    pintarVistaPrevia(respuesta);
                }
            });
        });

        /**
         * El segundo paso: importar de verdad, lo que se vio en la previa.
         */
        $(document).on('click', '#btnImportarTipoCambio', function () {
            limpiarErrorImportar();

            const $boton = $(this);
            $boton.prop('disabled', true);

            enviarFormulario(true, function (respuesta) {
                $boton.prop('disabled', false);

                Swal.fire({
                    title: 'Mes importado',
                    html: 'Se han guardado <strong>' + respuesta.guardados + '</strong> día(s).'
                        + '<br>'
                        + respuesta.nuevos + ' nuevos y ' + respuesta.cambiados + ' corregidos.'
                        + (respuesta.recuperados > 0
                            ? '<br>' + respuesta.recuperados + ' recuperados de días que estaban borrados.'
                            : ''),
                    icon: 'success',
                    confirmButtonText: 'Entendido',
                }).then(function () {
                    modalImportar?.hide();

                    recargarTabla();
                });
            });
        });

        function enviarFormulario(confirmar, alTerminar) {
            if (!archivoElegido) {
                mostrarErrorImportar('Vuelva a elegir el archivo.');

                return;
            }

            const datos = new FormData();

            datos.append('archivo', archivoElegido);
            datos.append('moneda_id', $('#monedaImportar').val());
            datos.append('fuente', $('#fuenteImportar').val());

            if (confirmar) {
                datos.append('confirmar', '1');
            }

            /*
             * El token va dentro del FormData y no solo en la cabecera.
             *
             * El layout de esta aplicacion no trae la meta etiqueta
             * csrf-token, asi que la cabecera sale vacia y el servidor
             * contesta 419: el boton no hace nada y no dice por que. Los
             * demas formularios de la aplicacion funcionan porque usan
             * serialize(), que ya arrastra el _token que lleva el campo
             * oculto del formulario.
             *
             * Aqui el FormData se arma a mano, porque un archivo no se puede
             * mandar por serialize(), y por eso hay que acordarse del token.
             * Se saca del propio formulario, que es donde esta.
             */
            datos.append('_token', $('#formImportarTipoCambio input[name="_token"]').val() || '');

            $.ajax({
                url: $formularioImportar.attr('action'),
                method: 'POST',
                data: datos,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                success: alTerminar,
                error: function (xhr) {
                    const respuesta = xhr.responseJSON || {};

                    if (respuesta.errors) {
                        const mensajes = Object.values(respuesta.errors).flat();

                        mostrarErrorImportar(mensajes.join('<br>'));
                    } else {
                        mostrarErrorImportar(
                            respuesta.message || 'No se pudo leer el archivo.'
                        );
                    }
                },
            });
        }

        function pintarVistaPrevia(datos) {
            const partes = [];

            partes.push(
                'El archivo trae <strong>' + datos.total + '</strong> día(s), del '
                + datos.desde.split('-').reverse().join('/') + ' al '
                + datos.hasta.split('-').reverse().join('/') + '.'
            );

            partes.push(
                '<strong>' + datos.nuevos + '</strong> nuevos y '
                + datos.cambian.length + ' que cambian de valor.'
            );

            if (datos.sin_cambiar > 0) {
                partes.push(
                    datos.sin_cambiar + ' ya estaban y se quedan como están.'
                );
            }

            /*
             * Los dias que estaban borrados y vuelven. Se dicen aparte porque
             * no es lo mismo que un dia nuevo: la fila ya estaba escrita y lo
             * que hace la importacion es revivirla, y quien lo borro quizas
             * lo borro porque no queria ese valor en la serie.
             */
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
                    + 'Ningún día cambia de valor: lo que trae el archivo es lo que ya hay.</td></tr>'
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

            if (datos.descartadas.length > 0) {
                const $detalle = $('#importarDescartadas').empty();

                const $lista = $('<ul class="mb-0 small"></ul>');

                datos.descartadas.forEach(function (una) {
                    $lista.append(
                        '<li>Fila ' + una.fila + ': ' + una.motivo + '</li>'
                    );
                });

                $detalle.append(
                    '<h6 class="mt-3 mb-2">Filas que no se importan</h6>'
                );

                $detalle.append($lista);
            }

            $resumen.removeClass('d-none');

            $('#btnVerTipoCambio').addClass('d-none');
            $('#btnImportarTipoCambio').removeClass('d-none');

            $('#textoImportarTipoCambio').text(
                'Importar ' + datos.total + ' día(s)'
            );
        }

        // ------------------------------------------------------------------
        // La plantilla de ejemplo
        // ------------------------------------------------------------------

        $(document).on('click', '#btnPlantillaTipoCambio', function (evento) {
            evento.preventDefault();

            /*
             * La url va en un data-url y no escrita aqui dentro, por la misma
             * razon que los botones de la fila: este archivo lo lee el
             * navegador tal cual, sin pasar por blade. Si la ruta se
             * escribiera a mano en el javascript, un cambio de prefijo de la
             * ruta daria un 404 en silencio y el usuario descargaria una
             * pagina de error SVN con extension csv.
             */
            window.location.href = $('#btnPlantillaTipoCambio').data('url') || '';
        });
    });
})(window.jQuery);
