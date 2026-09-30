/**
 * Tipos de cobro: alta, edicion, ficha, activar y desactivar, y borrado.
 *
 * Sigue el camino de las monedas: modales sobre la lista, con los datos pedidos
 * al servidor y puestos en el modal. No se copia nada del html de la fila a
 * mano, que es como se desincroniza una tabla con su detalle.
 *
 * LO QUE HAY AQUI Y NO EN LOS RESTO DE MODALES, Y NO ES UN DETALLE DE ESTILO.
 *
 *  - El interruptor de activo va con un campo hidden que manda 0 cuando esta
 *    desmarcado. Sin eso, una casilla sin marcar no manda nada, el servidor ve
 *    que el campo no vino, y "el usuario dijo que no" se confunde con "el
 *    usuario no toco nada". Con el hidden, desmarcado es un 0 explicito.
 *
 *    Y el hidden tiene que ir DESPUES de la casilla en el html, porque el
 *    javascript quita el atributo name a la casilla cuando esta marcada. Si
 *    fuera al reves, al desmarcar volveria a mandarse la casilla y el hidden,
 *    y el servidor veria dos valores.
 *
 *  - Activar y desactivar es un boton aparte en la fila y no un campo del
 *    formulario, y por dos motivos que estan escritos en el controlador: es la
 *    accion que mas se repite, y si estuviera en el modal habria que abrirlo,
 *    cambiar el interruptor y guardar —que es un modo de equivocarse, porque se
 *    acaba guardando el nombre con una tilde quitada mientras venia a cambiar el
 *    estado—.
 *
 *  - El boton de borrar no se esconde cuando la caja esta en uso, y el aviso
 *    dice cuantos cobros son. Con el boton ahi, el que tiene que desactivar
 *    una caja en vez de borrarlo ve la razon exacta y el numero de cobros que
 *    seVERN afectada. Es informacion que le sirve igual aunque no quisiera
 *    borrar.
 *
 *  - Y el boton de activar o desactivar NO lleva aviso. Es la accion de
 *    cambiar un estado y se puede volver a pulsar para lo contrario, asi que no
 *    hay nada que confirmar: un aviso por cada desactivacion es un aviso que
 *    la gente deja de leer, y este es justo el aviso que importa que se lea. Lo
 *    que si lleva es el texto de lo que va a pasar, en el title.
 */
(function ($) {
    'use strict';

    $(function () {
        const $formulario = $('#formCaja');

        if (!$formulario.length) {
            return;
        }

        const modalFormElement = document.getElementById('modalCaja');
        const modalForm = modalFormElement ? new bootstrap.Modal(modalFormElement) : null;

        const modalVerElement = document.getElementById('modalVerCaja');
        const modalVer = modalVerElement ? new bootstrap.Modal(modalVerElement) : null;

        const $error = $('#cajaError');

        // La caja que se esta viendo en la ficha, para el boton de editar
        let tipoEnLaFicha = null;

        // ------------------------------------------------------------------
        // Utilidades
        // ------------------------------------------------------------------

        function limpiarError() {
            $error.addClass('d-none').empty();
        }

        /**
         * Deja el interruptor como toca.
         *
         * Con el valor se marca o se quita la casilla, y en los dos casos se
         * quita el atributo name de la casilla y se la deja sin nombre, que es
         * como se hace que un checkbox sin marcar no mande nada. El hidden de
         * al lado es el que manda entonces el 0.
         *
         * Sin quitar el name, una casilla marcada y desmarcada dentro del mismo
         * envio mandarian los dos valores y el servidor leeria el ultimo, que es
         * justo el orden en que el navegador los manda pero no el que el
         * usuario quiere. Con una casilla sola no hay dos valores que leer.
         */
        function ponerEstado(marcado) {
            const $casilla = $('#estado');

            $casilla.prop('checked', !!marcado);

            if (marcado) {
                $casilla.removeAttr('name');
            } else {
                $casilla.attr('name', 'estado');
            }
        }

        function limpiarFormulario() {
            $formulario[0].reset();

            $formulario.find('.is-invalid').removeClass('is-invalid');
            $formulario.find('.invalid-feedback').hide().text('');

            $('#nombre').val('');
            $('#descripcion').val('');

            ponerEstado(true);

            $formulario.find('input[name="_method"]').remove();
        }

        /**
         * El mensaje del servidor, marcando el campo que fallo.
         *
         * Un error de validacion viene con un campo concreto. Se marca ese
         * campo, y no se suelta la lista en un bloque aparte, para que se vea
         * cual es sin tener que ir a buscarlo.
         *
         * El caso importante aqui es el de la caja en uso: el servidor lo manda
         * contra el campo del nombre, que es donde el usuario lo ha elegido, y
         * el texto es largo y dice por que y que se puede hacer. Si ese texto
         * se soltara en un bloque arriba, con el separador de lineas, seria el
         * mismo texto pero con saltos en medio de las frases, que es como se
         * lee peor.
         */
        function mostrarError(xhr) {
            const respuesta = xhr.responseJSON || {};

            if (!respuesta.errors) {
                $error
                    .removeClass('d-none')
                    .html(respuesta.message || 'No se pudo guardar el caja.');

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
        // Alta
        // ------------------------------------------------------------------

        $(document).on('click', '#btnNuevaCaja', function () {
            limpiarError();
            limpiarFormulario();

            $('#modalCajaLabel').text('Nuevo caja');
            $('#textoGuardarCaja').text('Guardar tipo');

            $formulario.attr('action', $formulario.data('store-url'));

            modalForm?.show();
        });

        // ------------------------------------------------------------------
        // Edicion
        // ------------------------------------------------------------------

        $(document).on('click', '.btn-edit-caja', function (evento) {
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

                    $('#modalCajaLabel').text('Editar el caja');
                    $('#textoGuardarCaja').text('Guardar cambios');

                    /*
                     * El method de mentira es lo que hace que un formulario
                     * normal envie un PUT. Antes estaba puesto para el alta y se
                     * quita a proposito: si se dejara, el alta llegaria como un
                     * PUT a la ruta de crear.
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

                    $('#nombre').val(datos.nombre ?? '');
                    $('#descripcion').val(datos.descripcion ?? '');

                    ponerEstado(datos.estado);

                    modalForm?.show();
                },
                error: function (xhr) {
                    mostrarError(xhr);
                    modalForm?.show();
                },
            });
        }

        // ------------------------------------------------------------------
        // Ficha
        // ------------------------------------------------------------------

        $(document).on('click', '.btn-ver-caja', function (evento) {
            evento.preventDefault();

            const url = $(this).data('url');

            if (!url) {
                return;
            }

            $.ajax({
                url: url,
                method: 'GET',
                success: function (datos) {
                    tipoEnLaFicha = datos;

                    $('#verCajaNombre').text(datos.nombre || '—');
                    $('#verCajaDescripcion').text(datos.descripcion || '—');

                    $('#verCajaEstado').html(datos.estado
                        ? '<span class="badge bg-success">Activo</span>'
                        : '<span class="badge bg-secondary">Inactivo</span>');

                    pintarUsos(datos.usos || [], datos.frase_de_usos);

                    modalVer?.show();
                },
                error: function () {
                    $('#verCajaAviso')
                        .removeClass('d-none')
                        .text('No se pudo cargar la ficha del caja.');

                    modalVer?.show();
                },
            });
        });

        /**
         * Donde se esta usando la caja, y si se puede borrar.
         *
         * La lista sale con la tabla y el numero al lado, que es la misma forma
         * que la tabla de usos de las monedas. Y debajo va el aviso que dice que
         * hacer, que es lo que de verdad importa: "no se puede borrar" sin decir
         * "desactivalo" deja al usuario sin salida.
         */
        function pintarUsos(usos, frase) {
            const $usos = $('#verCajaUsos').empty();
            const $aviso = $('#verCajaSePuedeBorrar');

            $aviso.addClass('d-none').empty();

            if (usos.length === 0) {
                $usos.html(
                    '<p class="text-muted mb-0">No hay ningún cobro con esta caja. '
                    + 'Se puede eliminar.</p>'
                );

                $aviso
                    .removeClass('d-none alert-info')
                    .addClass('alert-success')
                    .html(
                        '<i class="bi bi-check-circle me-1"></i>'
                        + 'Ningún cobro usa esta caja, así que se puede eliminar sin dejar nada a medias.'
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
                    + 'No se puede eliminar mientras tenga cobros, en ' + frase + '. '
                    + 'Se puede <strong>desactivar</strong>, que lo saca de los desplegables sin '
                    + 'tocar los cobros que ya se registraron con él, y se puede volver a activar.'
                );
        }

        // El boton de editar de la ficha abre el formulario con lo que ya esta
        // cargado, sin volver a pedirlo: los datos son los mismos que se
        // acaba de traer, y volver a pedirlos es una peticion que no cambia
        // nada entre medias salvo que alguien edite la caja en otra pestaña.
        $(document).on('click', '#btnEditarDesdeFicha', function () {
            if (!tipoEnLaFicha) {
                return;
            }

            modalVer?.hide();

            abrirEdicion(tipoEnLaFicha.__url || null);
        });

        // ------------------------------------------------------------------
        // Guardar
        // ------------------------------------------------------------------

        $formulario.on('submit', function (evento) {
            evento.preventDefault();

            limpiarError();

            const $boton = $('#btnGuardarCaja');
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
        // Activar y desactivar
        // ------------------------------------------------------------------

        /*
         * Sin aviso, a diferencia del borrado.
         *
         * Es lo que va a pasar lo que dice el title del boton, y el aviso de
         * SweetAlert lo reserva el borrar, que es el unico de los dos que no se
         * puede deshacer. Poner un aviso por cada desactivacion es un aviso que
         * la gente aprende a saltar, y este es justo el que importa que se lea.
         *
         * Y el boton no esta dentro de un form, asi que el metodo de la peticion
         * va aqui. Las demas pantallas hacen lo mismo con el boton de borrar.
         */
        $(document).on('click', '.btn-cambiar-estado-caja', function (evento) {
            evento.preventDefault();

            const $boton = $(this);

            $boton.prop('disabled', true);

            $.ajax({
                url: $boton.data('url'),
                method: 'POST',
                data: {
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

        // ------------------------------------------------------------------
        // Borrar
        // ------------------------------------------------------------------

        $(document).on('click', '[data-confirm-delete-caja]', function (evento) {
            evento.preventDefault();

            const $boton = $(this);
            const url = $boton.data('url');
            const nombre = $boton.data('nombre') || '';

            if (!url) {
                return;
            }

            /*
             * El aviso dice el nombre y avisa de lo que no tiene arreglo: si hay
             * cobros en esta caja el servidor lo va a rechazar, y ese rechazo es
             * la respuesta correcta —no es un fallo— porque el cobro guarda en
             * qué caja entró y sin ella no se podría saber.
             *
             * Y avisa del nombre y no del identificador porque el nombre puede
             * repetirse: con dos cajas que se llamen igual el aviso es el mismo
             * para las dos, y lo que las distingue es si tienen cobros.
             */
            Swal.fire({
                title: '¿Eliminar la caja "' + nombre + '"?',
                html: 'Se borra del catálogo.<br><br>'
                    + '<strong>Solo se puede si no hay ningún cobro en esta caja.</strong> '
                    + 'Si ya hay cobros, no se podrá borrar y habrá que desactivarla, '
                    + 'que la saca de los desplegables sin tocar lo ya registrado.',
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
                            : (respuesta.message || 'No se pudo eliminar la caja.');

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
            if ($.fn.dataTable.isDataTable('#cajas-table')) {
                $('#cajas-table').DataTable().ajax.reload();
            } else {
                window.location.reload();
            }
        }
    });
})(jQuery);
