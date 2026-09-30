/**
 * Valoraciones de oro: alta, edicion y borrado, y la cuenta antes de guardar.
 *
 * Sigue el camino de las recuperaciones: modal sobre la lista, con los datos
 * pedidos al servidor y puestos en el modal. No se copia nada del html de la
 * fila a mano, que es como se desincroniza una tabla con su detalle.
 *
 * LO QUE HAY AQUI Y NO EN LOS RESTO DE MODALES, Y NO ES UN DETALLE DE ESTILO.
 *
 *  - El modal no tiene campo de valor. El valor lo calcula el servidor y se
 *    escribe solo en el aviso de la cuenta. Por eso el boton de guardar esta
 *    apagado mientras no haya una cuenta que enseñar: si se dejara pulsar sin
 *    cuenta, el usuario guardaria sin ver de donde sale la cifra, que es
 *    justo lo que este formulario queria evitar. Con la cuenta a la vista
 *    antes de guardar, lo unico que queda por decidir es si el dia y las
 *    monedas son los correctos.
 *
 *  - La cuenta se pide al servidor, no se calcula aqui. El precio del gramo sale
 *    de una serie por dias —el ultimo conocido que no sea cero— y el tipo de
 *    cambio tambien. Calcularlo en el navegador tendria que mandarle la serie
 *    entera o estimar, y entre las dos cosas se acabaria viendo un numero en
 *    el aviso que no es el que se guarda. Preguntando, lo del aviso y lo de la
 *    fila los saca el mismo metodo con los mismos datos.
 *
 *  - Se pide cada vez que cambia el dia, la moneda del precio, la moneda del
 *    valor o la recuperacion. En el caso normal son cuatro clics, asi que no
 *    compensa hacerlo en cada tecla: lo que se evita es la peticion por letra
 *    en el campo de las observaciones.
 *
 *  - Y hay una carrera contra la respuesta. Si el usuario mueve el desplegable
 *    rapido puede llegar la respuesta de un dia viejo DESPUES que la del dia
 *    nuevo, y pintarla dejaria un numero que no corresponde a lo que hay en
 *    pantalla. Se resuelve guardando un numero de peticion y no pintando nada
 *    si la respuesta no es la ultima: el aviso se queda con lo que ya habia, o
 *    con un "calculando" si no habia nada. Es el mismo problema que el del
 *    guardado doble, y por mas raro que parezca se da.
 */
(function ($) {
    'use strict';

    $(function () {
        const $formulario = $('#formValoracion');

        if (!$formulario.length) {
            return;
        }

        const modalFormElement = document.getElementById('modalValoracion');
        const modalForm = modalFormElement ? new bootstrap.Modal(modalFormElement) : null;

        const $error = $('#valoracionError');
        const $aviso = $('#valoracionCalculo');
        const $lineas = $('#valoracionCalculoLineas');
        const $botonGuardar = $('#btnGuardarValoracion');

        /*
         * Donde pregunta al servidor la cuenta.
         *
         * Va en el propio formulario y no hardcodeado, por la misma razon que
         * la de guardar: si el prefijo de las rutas cambiara, el archivo
         * seguiria funcionando sin tocarlo.
         */
        const urlCalcular = $formulario.data('calcular-url');

        /*
         * El numero de la ultima peticion de calculo.
         *
         * Sube cada vez que se pide algo, y la respuesta solo se pinta si su
         * numero es el mismo que tiene este. Sin esto, con dos peticiones en
         * marcha, la que tarde mas se lleva la ultima palabra y el aviso
         * muestra la cuenta de un dia que ya no es el que hay elegido.
         */
        let peticionEnCurso = 0;

        // ------------------------------------------------------------------
        // Formato de los numeros
        // ------------------------------------------------------------------

        /*
         * Los numeros en como los ve el taller.
         *
         * Intl con es, y no number_format a mano, porque el separador de miles
         * y el decimal los pone el navegador segun como esta configurado el
         * equipo. En uno en español sale 7.050,00 y en otro 7,050.00, que es lo
         * que ha pedido siempre el taller. Y los gramos van con cuatro
         * decimales porque la columna es decimal(14,4): enseñar "2" donde se
         * guardan 2,0000 hace pensar que se perdio algo por el camino.
         */
        function numero(valor, decimales) {
            if (valor === null || valor === undefined || valor === '') {
                return '—';
            }

            const n = Number(valor);

            if (!isFinite(n)) {
                return '—';
            }

            return new Intl.NumberFormat('es', {
                minimumFractionDigits: decimales,
                maximumFractionDigits: decimales,
            }).format(n);
        }

        function fecha(texto) {
            if (!texto) {
                return '—';
            }

            const partes = String(texto).split('-');

            if (partes.length !== 3) {
                return texto;
            }

            return partes[2] + '/' + partes[1] + '/' + partes[0];
        }

        /**
         * El codigo de la moneda de un desplegable, para ponerlo en el aviso.
         *
         * Se saca del option que esta elegido y no de la lista de monedas del
         * servidor: el aviso se pinta con lo que hay en pantalla, que es lo
         * que el usuario ha elegido. Si el aviso dijera otra cosa, el numero
         * y la letra no harian juego.
         */
        function codigoDe(selector) {
            const valor = $(selector).val();

            if (!valor) {
                return '';
            }

            const opcion = $(selector).find('option[value="' + valor + '"]');

            if (opcion.length === 0) {
                return '';
            }

            return opcion.text().split('—')[0].trim();
        }

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

            $('#fechaValoracion').val('');
            $('#observaciones').val('');

            /*
             * El desplegable de la recuperacion se limpia a mano y no con
             * reset(), porque el reset no sabe lo que hay dentro de un select2:
             * deja escrito en pantalla el valor anterior mientras el campo real
             * ya esta vacio. Al abrir el modal de nuevo se veria "OT-2026-0001" y
             * al guardar se mandaria esa recuperacion sin haber elegido ninguna,
             * que es justo una valoracion en la orden equivocada.
             *
             * Y los de las dos monedas se dejan con su valor por defecto —el del
             * ultimo precio cargado, y la base— porque esos los pone el Blade y
             * son los que el servidor espera. Si se limpiaran, habria que
             * elegirlos a mano en cada alta para poder hacer nada.
             */
            if ($.fn.select2 && $('#recuperacion_id').data('select2')) {
                $('#recuperacion_id').val(null).trigger('change');
            } else {
                $('#recuperacion_id').val('');
            }

            $formulario.find('input[name="_method"]').remove();

            ocultarCuenta();
        }

        /**
         * El mensaje del servidor, marcando el campo que fallo.
         *
         * Un error de validacion viene con un campo concreto. Se marca ese
         * campo, y no se suelta la lista en un bloque aparte, para que se vea
         * cual es sin tener que ir a buscarlo.
         *
         * El caso importante aqui es el de la recuperacion ya valorada y el de
         * la orden cancelada: los dos son textos largos que dicen por que y que
         * se puede hacer, y llegan contra el selector de la recuperacion, que
         * es donde el usuario lo ha elegido. Si se soltaran en un bloque arriba,
         * con el separador de lineas, seria el mismo texto pero con saltos en
         * medio de las frases, que es como se lee peor.
         */
        function mostrarError(xhr) {
            const respuesta = xhr.responseJSON || {};

            if (!respuesta.errors) {
                $error
                    .removeClass('d-none')
                    .html(respuesta.message || 'No se pudo guardar la valoración.');

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
        // La cuenta
        // ------------------------------------------------------------------

        function ocultarCuenta() {
            $aviso.addClass('d-none');
            $lineas.empty();

            /*
             * Sin cuenta no hay boton.
             *
             * Es lo unico que impide guardar sin ver de donde sale la cifra. El
             * servidor lo comprobaria igual y devolveria el mismo aviso, pero
             * con el boton apagado el usuario ve que falta algo antes de
             * intentarlo, que es la diferencia entre "esto va mal" y "no me ha
             * dejado".
             */
            $botonGuardar.prop('disabled', true);
        }

        function pintandoCuenta() {
            $aviso
                .removeClass('d-none alert-danger alert-success alert-light')
                .addClass('alert-info');

            $lineas.html(
                '<div class="d-flex align-items-center gap-2">'
                + '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'
                + '<span class="small">Calculando…</span>'
                + '</div>'
            );
        }

        /**
         * Una linea de la cuenta: lo que se multiplica por lo que sea.
         *
         * Va con una flecha entre las dos mitades porque el sentido importa:
         * "1,5000 g por 4.700,0000 el gramo son 7.050,00" se lee, y las tres
         * cifras sueltas en una columna no dicen cual es el resultado.
         */
        function linea(izquierda, operador, derecha) {
            return '<div class="d-flex align-items-baseline gap-2 py-1">'
                + '<span class="text-truncate">' + izquierda + '</span>'
                + '<span class="text-muted">' + operador + '</span>'
                + '<span class="text-truncate">' + derecha + '</span>'
                + '</div>';
        }

        function pintarCuenta(c) {
            if (!c.ok) {
                $aviso
                    .removeClass('d-none alert-success alert-info alert-light')
                    .addClass('alert-danger');

                $lineas.html(
                    '<div class="d-flex align-items-start gap-2">'
                    + '<i class="bi bi-exclamation-triangle-fill"></i>'
                    + '<div><div class="fw-semibold">No se puede valorar todavía</div>'
                    + '<div class="small">' + c.motivo + '</div></div>'
                    + '</div>'
                );

                $botonGuardar.prop('disabled', true);

                return;
            }

            const codigoPrecio = codigoDe('#precio_moneda');
            const codigoValor = codigoDe('#moneda_id');

            /*
             * El titulo del valor va en grande y con la moneda pegada.
             *
             * Es la cifra por la que se abre el modal, asi que tiene que
             * destacar de las lineas de la cuenta: si todo con el mismo tamano,
             * el ojo no sabe donde esta el resultado y acaba leyendo la primera
             * linea, que es la de los gramos.
             */
            const cabeza = '<div class="d-flex align-items-baseline flex-wrap gap-2 mt-2 pt-2 border-top">'
                + '<span class="fs-5 fw-bold font-monospace">' + numero(c.valor, 2) + '</span>'
                + '<span class="text-muted">' + (codigoValor || '') + '</span>'
                + '</div>';

            $aviso
                .removeClass('d-none alert-danger alert-info alert-light')
                .addClass('alert-success');

            $lineas.html(
                linea(
                    '<strong>' + numero(c.gramos_valorados, 4) + ' g</strong>'
                    + (c.usa_pureza
                        ? ' <span class="text-muted small">de oro fino, el '
                            + numero(c.pureza * 100, 2) + ' % de los '
                            + numero(c.gramos, 4) + ' g que salieron</span>'
                        : ' <span class="text-muted small">que salieron'
                            + (c.pureza === null
                                ? ', sin medir la pureza'
                                : '')
                            + '</span>'),
                    '×',
                    '<strong>' + numero(c.precio_gramo, 4) + '</strong>'
                    + ' <span class="text-muted small">el gramo</span>'
                    + (c.unidad_precio && c.unidad_precio !== 'gramo'
                        ? ' <span class="text-muted small">(convertido desde '
                            + numero(c.precio, 4) + ' la ' + c.unidad_precio + ')</span>'
                        : '')
                    + (c.precio_del_dia !== c.fecha_del_calculo
                        ? ' <span class="text-muted small">del ' + fecha(c.precio_del_dia) + '</span>'
                        : ' <span class="text-muted small">de ese mismo día</span>')
                )
                + (c.tipo_cambio !== null && c.tipo_cambio !== undefined
                    ? linea(
                        '<span class="text-muted small">cambio de ese día</span>',
                        '×',
                        '<span class="font-monospace small">'
                            + numero(c.tipo_cambio, 4) + '</span>'
                            + ' <span class="text-muted small">'
                            + (codigoPrecio || '') + ' → ' + (codigoValor || '') + '</span>'
                    )
                    : '')
                + cabeza
            );

            $botonGuardar.prop('disabled', false);
        }

        /**
         * Pedir la cuenta al servidor y pintar lo que conteste.
         *
         * Si no hay recuperacion o no hay dia, no se pregunta nada: la cuenta
         * dependeria de un numero que todavia no existe y la respuesta seria un
         * error de validacion que el usuario no puede arreglar todavia.
         */
        function pedirCuenta() {
            const id = $('#recuperacion_id').val();
            const fechaValoracion = $('#fechaValoracion').val();

            if (!id || !fechaValoracion) {
                ocultarCuenta();

                return;
            }

            peticionEnCurso++;

            const estaPeticion = peticionEnCurso;

            pintandoCuenta();

            $.ajax({
                url: urlCalcular,
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    recuperacion_id: id,
                    fecha: fechaValoracion,
                    precio_moneda: $('#precio_moneda').val(),
                    moneda_id: $('#moneda_id').val(),
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                success: function (calculo) {
                    if (estaPeticion !== peticionEnCurso) {
                        return;
                    }

                    pintarCuenta(calculo);
                },
                error: function (xhr) {
                    if (estaPeticion !== peticionEnCurso) {
                        return;
                    }

                    const respuesta = xhr.responseJSON || {};

                    $aviso
                        .removeClass('d-none alert-success alert-info alert-light')
                        .addClass('alert-danger');

                    $lineas.html(
                        '<div class="small">'
                        + (respuesta.message || 'No se pudo calcular el valor.')
                        + '</div>'
                    );

                    $botonGuardar.prop('disabled', true);
                },
            });
        }

        /*
         * Que se repita la cuenta cuando cambie lo que la determina.
         *
         * El day del flatpickr dispara 'change' y no 'input', que es lo que
         * hace que funcione con el calendario y no solo escribiendo. Se queda
         * el que ademas se escucha el calendario, porque si el flatpickr se
         * quita alguna vez el boton de guardar se quedaria apagado sin motivo
         * aparente y no habria forma de saber por que.
         */
        $('#fechaValoracion').on('change', pedirCuenta);
        $('#fechaValoracion').on('input', pedirCuenta);
        $('#precio_moneda, #moneda_id').on('change', pedirCuenta);

        $(document).on('change', '#recuperacion_id', pedirCuenta);

        // ------------------------------------------------------------------
        // Alta
        // ------------------------------------------------------------------

        $(document).on('click', '#btnNuevaValoracion', function () {
            limpiarError();
            limpiarFormulario();

            $('#modalValoracionLabel').text('Nueva valoración de oro');
            $('#textoGuardarValoracion').text('Guardar valoración');

            $formulario.attr('action', $formulario.data('store-url'));

            modalForm?.show();

            /*
             * El dia de hoy por defecto.
             *
             * Es lo que se va a usar casi siempre: se valora lo que salio
             * hoy, o lo que salio hace unos dias. Y ponerlo evita el caso en que
             * el usuario elige la partida, ve que el boton esta apagado y no
             * entiende por que, cuando el unico motivo es que faltaba un campo
             * que el navegador puede poner solo.
             */
            if (!$('#fechaValoracion').val()) {
                const hoy = new Date();
                const mes = String(hoy.getMonth() + 1).padStart(2, '0');
                const dia = String(hoy.getDate()).padStart(2, '0');

                $('#fechaValoracion').val(hoy.getFullYear() + '-' + mes + '-' + dia);
            }

            pedirCuenta();
        });

        // ------------------------------------------------------------------
        // Edicion
        // ------------------------------------------------------------------

        $(document).on('click', '.btn-edit-valoracion', function (evento) {
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

                    $('#modalValoracionLabel').text('Editar la valoración');
                    $('#textoGuardarValoracion').text('Guardar cambios');

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

                    $('#fechaValoracion').val(datos.fecha ?? '');
                    $('#precio_moneda').val(datos.precio_moneda ?? '');
                    $('#moneda_id').val(datos.moneda_id ?? '');
                    $('#observaciones').val(datos.observaciones ?? '');

                    if ($.fn.select2 && $('#recuperacion_id').data('select2')) {
                        $('#recuperacion_id').val(datos.recuperacion_id ?? '').trigger('change');
                    } else {
                        $('#recuperacion_id').val(datos.recuperacion_id ?? '');
                    }

                    modalForm?.show();

                    /*
                     * La cuenta del servidor es la de HOY, no la que se guardo
                     * aquella vez.
                     *
                     * Y esa diferencia es el aviso, no un fallo: si el banco
                     * corrigio el precio de un dia despues, la valoracion
                     * guardada seCerro con el precio viejo y el modal enseña el
                     * nuevo. El boton de guardar se enciende y es el usuario
                     * quien decide si corrige la fila o la deja como estaba, que
                     * es justo lo que tiene que decidir alguien.
                     */
                    if (datos.calculo) {
                        pintarCuenta(datos.calculo);
                    } else {
                        pedirCuenta();
                    }
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

            $botonGuardar.prop('disabled', true);

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
                    $botonGuardar.prop('disabled', false);
                    mostrarError(xhr);
                },
            });
        });

        // ------------------------------------------------------------------
        // Borrar
        // ------------------------------------------------------------------

        $(document).on('submit', 'form[data-confirm-delete-valoracion]', function (evento) {
            evento.preventDefault();

            const $formularioBorrar = $(this);
            const fechaValoracion = $formularioBorrar.data('fecha') || '';
            const valor = $formularioBorrar.data('valor') || '';
            const moneda = $formularioBorrar.data('moneda') || '';

            /*
             * El aviso dice la cifra que se va, con su moneda, y avisa de que no
             * se puede volver atras.
             *
             * Y avisa de lo que no tiene arreglo: la valoracion no guarda los
             * gramos valorados, solo el precio que uso. Volver a tener esa cifra
             * obliga a revalorar desde el modal, que es volver a elegir el dia
             * y las dos monedas a mano. La recuperacion se queda, que es lo que
             * hace que sea recuperable: se borra la cifra, no los gramos.
             */
            Swal.fire({
                title: '¿Eliminar la valoración del ' + fechaValoracion + '?',
                html: 'Se borra el valor de <strong>'
                    + valor + (moneda ? ' ' + moneda : '')
                    + '</strong>.<br><br>Los gramos de la recuperación se quedan: '
                    + 'para volver a tener esta cifra habrá que valorar otra vez, '
                    + 'eligiendo el día y las monedas a mano.',
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
            if ($.fn.dataTable.isDataTable('#valoraciones-oro-table')) {
                $('#valoraciones-oro-table').DataTable().ajax.reload();
            } else {
                window.location.reload();
            }
        }
    });
})(jQuery);