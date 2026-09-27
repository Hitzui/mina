$(function () {
    const modalElement = document.getElementById('modalTrabajoEmpleado');
    const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
    const modalShowElement = document.getElementById('modalShowTrabajoEmpleado');
    const modalShow = modalShowElement ? new bootstrap.Modal(modalShowElement) : null;
    const modalEmpleadoElement = document.getElementById('modalSeleccionarEmpleado');
    const modalEmpleado = modalEmpleadoElement ? new bootstrap.Modal(modalEmpleadoElement) : null;

    const empleadoId = $('#trabajoEmpleadoId');
    const empleadoNombre = $('#trabajoEmpleadoNombre');
    const tipoPago = $('#trabajoTipoPago');
    const fecha = $('#trabajoFecha');
    const tarifa = $('#trabajoTarifa');
    const cantidad = $('#trabajoCantidad');
    const total = $('#trabajoTotal');
    const moneda = $('#trabajoMoneda');
    const vigencia = $('#trabajoVigencia');
    const error = $('#trabajoEmpleadoError');
    const tipoCambio = $('#trabajoTipoCambio');
    const tarifaNio = $('#trabajoTarifaNio');
    const totalNio = $('#trabajoTotalNio');
    let $formulario = $('#formTrabajoEmpleado');

    /**
     * Regla de calculo que envia el servidor para el tipo de pago
     * seleccionado. Solo se usa para previsualizar: al guardar, el
     * servidor la vuelve a aplicar y es la que manda.
     */
    const METODO_TARIFA = 'TARIFA';
    const METODO_CANTIDAD_X_TARIFA = 'CANTIDAD_X_TARIFA';
    let metodoCalculo = METODO_CANTIDAD_X_TARIFA;

    function aplicarMetodoCalculo(metodo) {
        metodoCalculo = metodo === METODO_TARIFA
            ? METODO_TARIFA
            : METODO_CANTIDAD_X_TARIFA;
    }

    /**
     * Select2 dibuja su propio combo y esconde el <select> nativo. Cuando
     * el valor se cambia por codigo con .val(), la libreria no se entera y
     * el usuario sigue viendo el valor anterior: hay que relanzar change
     * para que actualice lo que muestra.
     *
     * El relanzamiento se hace con la bandera "sincronizando" en alto para
     * no disparar la consulta de tarifa. Al editar, el total guardado se
     * respeta y no se recalcula con la tarifa vigente del dia.
     */
    let sincronizando = false;

    function sincronizarSelect2($select) {
        if (!$select.length || $select.data('select2') === undefined) {
            return;
        }

        sincronizando = true;
        $select.trigger('change');
        sincronizando = false;
    }

    /**
     * Fija el valor de un combo y deja la pantalla al dia, sea nativo o
     * de Select2.
     */
    function fijar($select, valor) {
        $select.val(valor ?? '');
        sincronizarSelect2($select);
    }

    /**
     * El combo de tipo de pago arranca deshabilitado: no se elige sin
     * empleado. Select2 4.0.13 observa el atributo disabled con un
     * MutationObserver, asi que basta con cambiar la propiedad para que
     * el combo se vea deshabilitado tambien.
     */
    function habilitarTipoPago(habilitado) {
        tipoPago.prop('disabled', !habilitado);
        $('#trabajoTipoPagoAyuda').toggleClass('d-none', habilitado);
    }

    $('#btnNuevoTrabajoEmpleado').on('click', function () {
        limpiarFormulario();
        $('#modalTrabajoEmpleadoLabel').text('Nuevo trabajo de empleado');
        $formulario.attr('action', $formulario.data('store-url'));
        $formulario.find('input[name="_method"]').remove();
        modal?.show();
    });

    $('#btnSeleccionarEmpleado').on('click', function () {
        $('#btnConfirmarEmpleado').prop('disabled', true);
        $('#empleados-selector-table input.empleado-selector').prop('checked', false);
        modalEmpleado?.show();
    });

    $(document).on('change', '#empleados-selector-table input.empleado-selector', function () {
        $('#empleados-selector-table input.empleado-selector').not(this).prop('checked', false);
        $('#btnConfirmarEmpleado').prop('disabled', false);
    });

    $('#btnConfirmarEmpleado').on('click', function () {
        const seleccionado = $('#empleados-selector-table input.empleado-selector:checked');

        if (!seleccionado.length) {
            return;
        }

        empleadoId.val(seleccionado.val());
        empleadoNombre.val(seleccionado.data('nombre'));
        habilitarTipoPago(true);
        fijar(tipoPago, '');
        limpiarTarifa();
        limpiarError();
        modalEmpleado?.hide();
    });

    $(document).on('click', '.btn-show-trabajo', function () {
        const url = $(this).data('url');

        if (!url) {
            return;
        }

        limpiarModalShow();

        $.ajax({
            url: url,
            type: 'GET',
            success: function (response) {
                $('#showTrabajoEmpleado').val(response.empleado ?? '—');
                $('#showTrabajoFecha').val(response.fecha ?? '—');
                $('#showTrabajoTipoPago').val(response.tipo_pago ?? '—');
                $('#showTrabajoProceso').val(response.proceso ?? '—');
                $('#showTrabajoHoraInicio').val(response.hora_inicio ?? '—');
                $('#showTrabajoHoraFin').val(response.hora_fin ?? '—');
                $('#showTrabajoCantidad').val(response.cantidad ?? '0.00');
                $('#showTrabajoUnidad').val(response.unidad ?? '—');
                $('#showTrabajoTarifa').val(response.tarifa ?? '0.00');
                $('#showTrabajoMoneda').val(response.moneda ?? '—');
                $('#showTrabajoTipoCambio').val(response.tipo_cambio ?? '1.0000');
                $('#showTrabajoTarifaNio').val(response.tarifa_nio ?? '0.0000');
                $('#showTrabajoTotal').val(response.total ?? '0.00');
                $('#showTrabajoTotalNio').val(response.total_nio ?? '0.00');
                $('#showTrabajoDescripcion').val(response.descripcion ?? '');
                $('#showTrabajoObservaciones').val(response.observaciones ?? '');

                modalShow?.show();
            },
            error: function (xhr) {
                const mensaje = xhr.responseJSON?.message ?? 'No se pudo obtener la información del trabajo.';
                mostrarError(mensaje);
            }
        });
    });

    $(document).on('click', '.btn-edit-trabajo', function (e) {
        e.preventDefault();

        const url = $(this).data('url');

        if (!url) {
            return;
        }

        $.ajax({
            url: url,
            type: 'GET',
            success: function (response) {
                limpiarError();

                $('#modalTrabajoEmpleadoLabel').text('Editar trabajo de empleado');

                empleadoId.val(response.empleado_id);
                empleadoNombre.val(response.empleado ?? '—');
                habilitarTipoPago(true);
                fijar(tipoPago, response.tipo_pago_id);
                // El proceso no se toca: viene de la url de la pantalla
                fecha.val(response.fecha ?? '');
                $('#trabajoHoraInicio').val(response.hora_inicio ?? '');
                $('#trabajoHoraFin').val(response.hora_fin ?? '');
                cantidad.val(response.cantidad ?? 0);
                fijar($('#trabajoUnidad'), response.unidad ?? 'hora');
                $('#trabajoDescripcion').val(response.descripcion ?? '');
                $('#trabajoObservaciones').val(response.observaciones ?? '');
                tarifa.val(response.tarifa ?? 0);
                tipoCambio.val(response.tipo_cambio ?? 1);
                tarifaNio.val(response.tarifa_nio ?? 0);

                // Al editar se usa la regla del tipo de pago del registro
                aplicarMetodoCalculo(response.metodo_calculo);

                total.val(response.total ?? 0);
                totalNio.val(response.total_nio ?? 0);

                $formulario.attr('action', $formulario.data('update-url').replace('__ID__', response.id));
                $formulario.find('input[name="_method"]').remove();
                $formulario.append('<input type="hidden" name="_method" value="PUT">');

                modal?.show();
            },
            error: function (xhr) {
                const mensaje = xhr.responseJSON?.message ?? 'No se pudo obtener la información del trabajo.';
                mostrarError(mensaje);
            }
        });
    });


    tipoPago.on('change', obtenerTarifa);
    fecha.on('change', obtenerTarifa);
    cantidad.on('input', calcularTotal);
    tarifa.on('input', calcularTotal);

    function obtenerTarifa() {
        // Un .val() hecho por codigo no debe consultar la tarifa: al
        // editar, el total guardado manda y no se recalcula.
        if (sincronizando) {
            return;
        }

        const empleado = empleadoId.val();
        const tipo = tipoPago.val();
        const fechaTrabajo = fecha.val();

        limpiarError();

        if (!empleado || !tipo || !fechaTrabajo) {
            limpiarTarifa();
            return;
        }

        tarifa.prop('disabled', true);
        vigencia.text('Consultando tarifa...');

        $.ajax({
            url: $formulario.data('tarifa-url'),
            type: 'GET',
            data: {
                empleado_id: empleado,
                tipo_pago_id: tipo,
                fecha: fechaTrabajo
            },
            success: function (response) {
                tarifa.val(response.tarifa ?? 0);
                moneda.text(response.moneda ?? '—');
                tipoCambio.val(response.tipo_cambio ?? 1);
                tarifaNio.val(response.tarifa_nio ?? 0);

                // El servidor indica como calcular el total de este tipo de pago
                aplicarMetodoCalculo(response.metodo_calculo);

                if (response.fecha_inicio) {
                    vigencia.text('Vigente desde ' + response.fecha_inicio + (response.fecha_fin ? ' hasta ' + response.fecha_fin : ''));
                } else {
                    vigencia.text('');
                }

                if (!response.tipo_cambio_encontrado) {
                    mostrarError('No se encontró tipo de cambio para ' + response.moneda + ' en la fecha seleccionada. Se utilizará 1.0000 como respaldo.');
                }

                calcularTotal();
            },
            error: function (xhr) {
                limpiarTarifa();

                const mensaje = xhr.responseJSON?.message ?? 'No se encontró una tarifa vigente para el empleado y tipo de pago seleccionados.';

                mostrarError(mensaje);
            },
            complete: function () {
                tarifa.prop('disabled', false);
            }
        });
    }

    function calcularTotal() {
        const cantidadValor = parseFloat(cantidad.val()) || 0;
        const tarifaValor = parseFloat(tarifa.val()) || 0;
        const tarifaNioValor = parseFloat(tarifaNio.val()) || 0;

        /*
         * Con la regla TARIFA (pagos "por trabajo" o "fijo") la tarifa
         * ES el pago y la cantidad no multiplica: 8 horas a C$700 son
         * C$700, no C$5,600. La cantidad se conserva como dato.
         */
        const multiplica = metodoCalculo === METODO_CANTIDAD_X_TARIFA;

        const totalValor = multiplica
            ? cantidadValor * tarifaValor
            : tarifaValor;

        const totalNioValor = multiplica
            ? cantidadValor * tarifaNioValor
            : tarifaNioValor;

        total.val(totalValor.toFixed(2));
        totalNio.val(totalNioValor.toFixed(2));
    }

    function limpiarFormulario() {
        $formulario[0]?.reset();

        /*
         * form.reset() devuelve los <select> a su valor original, pero
         * Select2 no se entera: hay que sincronizar los combos al final,
         * cuando ya se les fijo el valor de arranque.
         */
        empleadoId.val('');
        empleadoNombre.val('');
        fijar(tipoPago, '');
        habilitarTipoPago(false);
        fecha.val(new Date().toISOString().slice(0, 10));
        tarifa.val(0);
        cantidad.val(0);
        total.val(0);
        moneda.text('—');
        vigencia.text('');

        if (typeof flatpickr !== 'undefined') {
            flatpickr('#trabajoFecha', {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd/m/Y',
                locale: 'es',
                allowInput: true,
                defaultDate: new Date()
            });

            flatpickr('#trabajoHoraInicio, #trabajoHoraFin', {
                enableTime: true,
                noCalendar: true,
                dateFormat: 'H:i',
                time_24hr: true,
                minuteIncrement: 5
            });
        }

        tipoCambio.val(1);
        tarifaNio.val(0);
        totalNio.val(0);

        // El reset tambien toca el combo de unidad: se deja como debe verse
        $formulario.find('select.select2').each(function () {
            sincronizarSelect2($(this));
        });

        limpiarError();
    }

    function limpiarTarifa() {
        tarifa.val(0);
        total.val(0);
        moneda.text('—');
        vigencia.text('');
        tipoCambio.val(1);
        tarifaNio.val(0);
        totalNio.val(0);

        // Sin tarifa no hay regla: se vuelve al comportamiento por defecto
        aplicarMetodoCalculo(null);
    }

    function limpiarModalShow() {
        $('#showTrabajoEmpleado').val('');
        $('#showTrabajoFecha').val('');
        $('#showTrabajoTipoPago').val('');
        $('#showTrabajoProceso').val('');
        $('#showTrabajoHoraInicio').val('');
        $('#showTrabajoHoraFin').val('');
        $('#showTrabajoCantidad').val('');
        $('#showTrabajoUnidad').val('');
        $('#showTrabajoTarifa').val('');
        $('#showTrabajoMoneda').val('');
        $('#showTrabajoTipoCambio').val('');
        $('#showTrabajoTarifaNio').val('');
        $('#showTrabajoTotal').val('');
        $('#showTrabajoTotalNio').val('');
        $('#showTrabajoDescripcion').val('');
        $('#showTrabajoObservaciones').val('');
    }

    function mostrarError(mensaje) {
        error.removeClass('d-none').text(mensaje);
    }

    function limpiarError() {
        error.addClass('d-none').text('');
    }
});
