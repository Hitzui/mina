/**
 * Ingresos de la orden: alta, edicion y ficha.
 *
 * El total lo previsualiza el multiplicando la cantidad por el precio
 * unitario, que es una multiplicacion y no necesita nada del servidor, para
 * que el usuario vea la cifra mientras escribe. Al guardar lo vuelve a
 * calcular el servidor —que es el que manda, y por eso el total no se manda en
 * el formulario— y la pagina se recarga entera, que es lo que hace tambien
 * el de los costos.
 *
 * El equivalente en córdoba NO se reimplementa aqui a proposito: necesita el
 * tipo de cambio de la fecha, y pedirlo al servidor en cada tecla seria una
 * consulta por pulsacion. Se muestra el que devolvio el ultimo guardado, para
 * que quien corrige un ingreso vea la cuenta anterior.
 *
 * Y hay una cosa que los costos no tienen y aqui si: el AVISO DE LA MONEDA. Si
 * el ingreso no es en cordoba, avisa de que el equivalente se va a calcular con
 * el tipo de cambio DE LA FECHA DEL INGRESO y no con el de hoy, y de que ese
 * numero queda guardado aunque luego se corrija el tipo de cambio.
 *
 * Eso es lo que mas caro sale en un taller que cobra en dolares y lleva la
 * contabilidad en cordoba: un ingreso del dia 3 convertido con el cambio de hoy
 * en vez de con el del dia 3 cambia lo facturado de toda la orden, y el error
 * no se ve hasta el trimestre siguiente, cuando la cuenta no cuadra y nadie
 * sabe desde cuando. El aviso no lo evita —el calculo lo hace el servidor
 * igual— pero dice lo que va a pasar antes de que pase, que es lo que hace
 * falta para poder mirar la fecha antes de guardar.
 */
(function ($) {
    'use strict';

    $(function () {
        const $formulario = $('#formIngreso');

        if (!$formulario.length) {
            return;
        }

        const modalFormElement = document.getElementById('modalIngreso');
        const modalForm = modalFormElement ? new bootstrap.Modal(modalFormElement) : null;

        const modalShowElement = document.getElementById('modalShowIngreso');
        const modalShow = modalShowElement ? new bootstrap.Modal(modalShowElement) : null;

        const $tipo = $('#ingresoTipo');
        const $campoFecha = $('#ingresoFecha');
        const $moneda = $('#ingresoMoneda');
        const $cantidad = $('#ingresoCantidad');
        const $unitario = $('#ingresoUnitario');
        const $total = $('#ingresoTotal');
        const $totalMoneda = $('#ingresoTotalMoneda');
        const $totalNio = $('#ingresoTotalNio');
        const $tipoCambio = $('#ingresoTipoCambio');
        const $aviso = $('#ingresoAviso');
        const $error = $('#ingresoError');

        // ------------------------------------------------------------------
        // Utilidades
        // ------------------------------------------------------------------

        /**
         * Un numero en como lo ve el taller.
         *
         * Intl con es, y no un formato a mano, porque el separador de miles y el
         * decimal los pone el navegador segun como este configurado el equipo.
         * En uno en espanol sale 7.050,00 y en otro 7,050.00, que no es como
         * se escriben aqui los importes.
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

        /**
         * "2026-09-30" en "30/09/2026", que es como se leen las fechas aqui.
         */
        function cuandoEsFecha(texto) {
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
         * Pone la fecha en el campo, y no en el de atras.
         *
         * El calendario de las fechas lo monta public/js/fechas.js sobre todos
         * los campos type="date" de la pagina, y lo hace con altInput: eso
         * quiere decir que hay DOS campos, el de verdad —oculto, con el
         * "2026-09-30" que va al servidor— y el de delante —el que se ve, con el
         * "30/09/2026"—.
         *
         * Poner el valor con .val() escribe solo en el de verdad, que es el
         * oculto, y el usuario sigue viendo la fecha anterior. Es un fallo que
         * no da ningun error: el campo acepta la fecha, el formulario la envia
         * bien, y lo que se ve esta equivocado. Por eso se llama a
         * setDate del calendario, que actualiza los dos.
         *
         * Y si el campo no tiene calendario —el layout no cargo flatpickr—
         * se escribe a mano, que es lo de antes.
         */
        function ponerFecha(valor) {
            const campo = $campoFecha[0];

            if (!campo) {
                return;
            }

            if (campo._flatpickr) {
                campo._flatpickr.setDate(valor || null, true);

                return;
            }

            $campoFecha.val(valor || '');
        }

        /**
         * Select2 dibuja su propio combo y esconde el select nativo. Cuando el
         * valor se cambia por codigo con .val(), la libreria no se entera y el
         * usuario sigue viendo el valor anterior: hay que relanzar change para
         * que actualice lo que muestra.
         */
        function sincronizarSelect2($select) {
            if (!$select.length || $select.data('select2') === undefined) {
                return;
            }

            $select.trigger('change');
        }

        function fijar($select, valor) {
            $select.val(valor === null || valor === undefined ? '' : valor);
            sincronizarSelect2($select);
        }

        /**
         * Si la moneda elegida es la base del taller.
         *
         * Se lee del data-base de la option y no de una lista escrita aqui: la
         * base la cambia el usuario en Configuracion, y una lista escrita en
         * este archivo se queda vieja, que es como se acabaria diciendo "no
         * hace falta tipo de cambio" para una moneda que si lo necesita.
         */
        function esMonedaBase() {
            const opcion = $moneda.find('option:selected');

            return opcion.length > 0 && opcion.data('base') === 1;
        }

        function codigoDeMoneda() {
            const opcion = $moneda.find('option:selected');

            return opcion.length ? opcion.text().split('—')[0].trim() : '—';
        }

        // ------------------------------------------------------------------
        // La cuenta
        // ------------------------------------------------------------------

        /**
         * El total es cantidad por precio unitario. Solo se previsualiza: al
         * guardar lo vuelve a calcular el servidor y es el que queda.
         */
        function calcularTotal() {
            const cantidadValor = parseFloat($cantidad.val()) || 0;
            const unitarioValor = parseFloat($unitario.val()) || 0;

            $total.val((cantidadValor * unitarioValor).toFixed(2));
            $totalMoneda.text(codigoDeMoneda());
        }

        /**
         * El aviso de la moneda, que es lo unico que esta pantalla tiene de mas
         * sobre los costos.
         *
         * Con la base no se dice nada, porque no hay nada que avisar: no se
         * aplica ningun tipo de cambio y el equivalente es el mismo numero. Y
         * con una moneda que no sea la base se dice una cosa y solo una: que el
         * cambio es el de la fecha del ingreso y que ese numero queda guardado.
         */
        function revisarAviso() {
            if (esMonedaBase()) {
                $aviso.addClass('d-none').text('');
                $tipoCambio.text('1');

                return;
            }

            const dia = $campoFecha.val();

            $aviso
                .removeClass('d-none')
                .addClass('alert-info')
                .html(
                    '<i class="bi bi-info-circle me-1"></i>'
                    + 'El equivalente en córdobas se va a calcular con el tipo de cambio '
                    + '<strong>del ' + (dia ? cuandoEsFecha(dia) : 'día del ingreso') + '</strong>, no con el de hoy, '
                    + 'y ese número queda guardado aunque luego se corrija el tipo de cambio.'
                    + '<br><span class="text-muted small">Si no hay ningún tipo de cambio de esa moneda '
                    + 'para esa fecha, el ingreso se guarda igual pero el equivalente se queda '
                    + 'vacío y no cuenta en el total de la orden.</span>'
                );

            $tipoCambio.text('—');
        }

        function limpiarPrevisualizacion() {
            $total.val('0.00');
            $totalMoneda.text(codigoDeMoneda());
            $totalNio.val('—');
            $tipoCambio.text('—');
        }

        function limpiarAviso() {
            $aviso.addClass('d-none').text('');
        }

        // ------------------------------------------------------------------
        // Los botones
        // ------------------------------------------------------------------

        $('#btnNuevoIngreso').on('click', function () {
            $formulario[0].reset();

            fijar($tipo, '');
            fijar($moneda, $moneda.find('option[data-base="1"]').val() ?? '');

            $cantidad.val('1');
            $unitario.val('0.00');

            limpiarPrevisualizacion();
            limpiarAviso();
            $error.addClass('d-none').text('');

            $('#modalIngresoLabel').text('Registrar ingreso');

            $formulario.attr('action', $formulario.data('store-url'));
            $formulario.find('input[name="_method"]').remove();

            calcularTotal();
            revisarAviso();

            modalForm?.show();
        });

        $(document).on('click', '.btn-show-ingreso', function (evento) {
            evento.preventDefault();

            const url = $(this).data('url');

            if (!url) {
                return;
            }

            $.ajax({
                url: url,
                type: 'GET',
                success: function (r) {
                    $('#showIngresoTipo').val(r.tipo ?? '—');
                    $('#showIngresoFecha').val(cuandoEsFecha(r.fecha));
                    $('#showIngresoDescripcion').val(r.descripcion ?? '');
                    $('#showIngresoCantidad').val(numero(r.cantidad, 4));
                    $('#showIngresoUnidad').val(r.unidad_medida ?? '—');
                    $('#showIngresoUnitario').val(numero(r.precio_unitario, 2));
                    $('#showIngresoTotal').val(numero(r.total, 2));
                    $('#showIngresoMoneda').val(r.moneda ?? '—');
                    $('#showIngresoObservaciones').val(r.observaciones ?? '');

                    /*
                     * El equivalente en cordoba va con el cambio con el que
                     * salio, y si no hay equivalente se dice por que en vez de
                     * dejar un guion suelto: un guion no explica nada y lo deja
                     * como si faltara el dato.
                     */
                    if (r.total_nio === null || r.total_nio === undefined) {
                        $('#showIngresoTotalNio').val('—');

                        pintarAvisoFicha(
                            '<i class="bi bi-exclamation-triangle me-1"></i>'
                            + 'Este ingreso no tiene equivalente en córdobas: no se encontró tipo '
                            + 'de cambio de su moneda para su fecha. El total en su moneda sí está, '
                            + 'pero no cuenta en el total de la orden.',
                            'alert-warning'
                        );

                        modalShow?.show();

                        return;
                    }

                    $('#showIngresoTotalNio').val(numero(r.total_nio, 2));

                    pintarAvisoFicha(
                        '<i class="bi bi-info-circle me-1"></i>'
                        + 'Calculado con el tipo de cambio de <strong>'
                        + (r.fecha ?? 'su fecha') + '</strong>: 1 '
                        + (r.moneda ?? '') + ' = ' + numero(r.tipo_cambio, 4) + ' NIO. '
                        + 'Ese número queda guardado aunque el tipo de cambio se corrija después.',
                        'alert-info'
                    );

                    modalShow?.show();
                },
                error: function () {
                    $error.text('No se pudo cargar el ingreso.');
                    modalShow?.show();
                },
            });
        });

        function pintarAvisoFicha(html, clase) {
            $('#showIngresoAviso')
                .removeClass('d-none alert-warning alert-info')
                .addClass(clase)
                .html(html);
        }

        $(document).on('click', '.btn-edit-ingreso', function (evento) {
            evento.preventDefault();

            const url = $(this).data('url');

            if (!url) {
                return;
            }

            $.ajax({
                url: url,
                type: 'GET',
                success: function (r) {
                    $error.addClass('d-none').text('');
                    limpiarAviso();

                    $('#modalIngresoLabel').text('Editar el ingreso');

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
                        $formulario.data('update-url').replace('__ID__', r.id)
                    );

                    fijar($tipo, r.tipo_ingreso_id);
                    fijar($moneda, r.moneda_id);

                    ponerFecha(r.fecha);

                    $('#ingresoDescripcion').val(r.descripcion ?? '');
                    $cantidad.val(r.cantidad ?? '');
                    $('#ingresoUnidad').val(r.unidad_medida ?? '');
                    $unitario.val(r.precio_unitario ?? '');
                    $('#ingresoObservaciones').val(r.observaciones ?? '');

                    calcularTotal();
                    revisarAviso();

                    /*
                     * El equivalente en cordoba que se guardo la ultima vez. Se
                     * muestra para que quien corrige vea la cuenta anterior: si
                     * al cambiar el dia o la moneda el nuevo sale distinto, esa
                     * diferencia es justo lo que se esta mirando.
                     */
                    $totalNio.val(numero(r.total_nio, 2));

                    modalForm?.show();
                },
                error: function () {
                    $error.text('No se pudo cargar el ingreso.');
                    modalShow?.show();
                },
            });
        });

        // ------------------------------------------------------------------
        // Los eventos
        // ------------------------------------------------------------------

        // El total se recalcula mientras se escribe, en los dos campos de los
        // que sale. La unidad no entra en la multiplicacion y la descripcion no
        // entra en nada, asi que no escuchan nada.
        [$cantidad, $unitario].forEach(function ($campo) {
            $campo.on('input change', calcularTotal);
        });

        // Y el aviso cuando cambia la fecha o la moneda, que son las dos cosas
        // de las que depende.
        [$campoFecha, $moneda].forEach(function ($campo) {
            $campo.on('change', function () {
                calcularTotal();
                revisarAviso();
            });
        });

        $tipo.on('change', function () {
            sincronizarSelect2($tipo);
        });

        /*
         * El envio es el normal, sin ajax, igual que el de los costos: el
         * servidor responde con una redireccion a la ficha y la pagina se
         * recarga, que es lo que hay que hacer para que la tabla se actualice.
         *
         * Lo unico que se hace aqui es desactivar el boton, porque un doble
         * click guarda dos ingresos.
         */
        $formulario.on('submit', function () {
            $('#btnGuardarIngreso').prop('disabled', true);
        });

        /*
         * Y cuando la pagina vuelve desde la cache del navegador —el boton
         * atras del movil, o ese aviso de "confirmar de nuevo" que sale al
         * mandar atras— el boton se queda en gris, porque el estado del
         * formulario tambien se guardo. pageshow con persisted es el aviso de
         * que se esta restaurando de la cache, que es justo el caso.
         *
         * Sin esto, un error de validacion que llegue por la cache deja el
         * formulario con el boton apagado y no se puede reintentar sin recargar
         * a mano.
         */
        window.addEventListener('pageshow', function (evento) {
            if (evento.persisted) {
                $('#btnGuardarIngreso').prop('disabled', false);
            }
        });
    });
})(jQuery);
