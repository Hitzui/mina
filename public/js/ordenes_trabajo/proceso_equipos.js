/**
 * Uso de un equipo dentro de un proceso.
 *
 * El equipo y las fechas se combinan para preguntar al servidor cuanto
 * costaria el periodo. La cifra se muestra antes de guardar para que el
 * usuario vea la depreciacion mientras decide, no despues. Al guardar,
 * el servidor la vuelve a calcular y es la que manda: esto es solo una
 * previsualizacion.
 */
$(function () {
    const modalElement = document.getElementById('modalUsoEquipo');
    const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
    const modalShowElement = document.getElementById('modalShowUsoEquipo');
    const modalShow = modalShowElement ? new bootstrap.Modal(modalShowElement) : null;

    const equipo = $('#usoEquipo');
    const fechaInicio = $('#usoFechaInicio');
    const fechaFin = $('#usoFechaFin');
    const sigueAsignado = $('#usoSigueAsignado');
    const dias = $('#usoDias');
    const tasaDiaria = $('#usoTasaDiaria');
    const depreciacion = $('#usoDepreciacion');
    const codigoEquipo = $('#usoCodigoEquipo');
    const vidaUtil = $('#usoVidaUtil');
    const aviso = $('#usoAviso');
    const error = $('#usoEquipoError');

    let $formulario = $('#formUsoEquipo');

    /**
     * Select2 dibuja su propio combo y esconde el <select> nativo. Cuando
     * el valor se cambia por codigo con .val(), la libreria no se entera y
     * el usuario sigue viendo el valor anterior: hay que relanzar change
     * para que actualice lo que muestra.
     */
    function sincronizarSelect2($select) {
        if (!$select.length || $select.data('select2') === undefined) {
            return;
        }

        $select.trigger('change');
    }

    $('#btnNuevoUsoEquipo').on('click', function () {
        limpiarFormulario();
        $('#modalUsoEquipoLabel').text('Asignar equipo al proceso');
        $formulario.attr('action', $formulario.data('store-url'));
        $formulario.find('input[name="_method"]').remove();
        modal?.show();
    });

    $(document).on('click', '.btn-show-uso', function () {
        const url = $(this).data('url');

        if (!url) {
            return;
        }

        limpiarModalShow();

        $.ajax({
            url: url,
            type: 'GET',
            success: function (response) {
                $('#showUsoEquipo').val(response.equipo ?? '—');
                $('#showUsoCodigo').val(response.codigo ?? '—');
                $('#showUsoProceso').val(response.proceso ?? '—');
                $('#showUsoDias').val(response.dias ?? '—');
                $('#showUsoInicio').val(formatearFechaHora(response.fecha_inicio));
                $('#showUsoFin').val(response.fecha_fin
                    ? formatearFechaHora(response.fecha_fin)
                    : 'Sigue asignado');
                $('#showUsoTasa').val(numero(response.depreciacion_diaria, 4));
                $('#showUsoDepreciacion').val(numero(response.depreciacion_total, 2));
                $('#showUsoObservaciones').val(response.observaciones ?? '');

                modalShow?.show();
            },
            error: function (xhr) {
                mostrarError(xhr.responseJSON?.message ?? 'No se pudo obtener la información del uso del equipo.');
            }
        });
    });

    $(document).on('click', '.btn-edit-uso', function (e) {
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
                limpiarAviso();

                $('#modalUsoEquipoLabel').text('Editar uso del equipo');

                // El proceso no se toca: viene de la url de la pantalla
                equipo.val(response.equipo_id);
                sincronizarSelect2(equipo);

                fechaInicio.val(response.fecha_inicio ?? '');
                fechaFin.val(response.fecha_fin ?? '');

                sigueAsignado.prop('checked', !response.fecha_fin);
                fechaFin.prop('disabled', !response.fecha_fin);

                $('#usoObservaciones').val(response.observaciones ?? '');

                $formulario.attr('action', $formulario.data('update-url').replace('__ID__', response.id));
                $formulario.find('input[name="_method"]').remove();
                $formulario.append('<input type="hidden" name="_method" value="PUT">');

                consultarDepreciacion();

                modal?.show();
            },
            error: function (xhr) {
                mostrarError(xhr.responseJSON?.message ?? 'No se pudo obtener la información del uso del equipo.');
            }
        });
    });

    equipo.on('change', function () {
        limpiarError();
        consultarDepreciacion();
    });

    fechaInicio.on('change', function () {
        limpiarError();
        consultarDepreciacion();
    });

    fechaFin.on('change', function () {
        limpiarError();
        consultarDepreciacion();
    });

    /**
     * "Sigue asignado" deja la fecha de fin vacia, que es como se
     * guarda: un equipo con el fin en NULL sigue en ese proceso y bloquea
     * cualquier uso posterior del mismo equipo.
     */
    sigueAsignado.on('change', function () {
        if (this.checked) {
            fechaFin.val('');
        }

        fechaFin.prop('disabled', this.checked);
        limpiarError();
        consultarDepreciacion();
    });

    /**
     * Le pregunta al servidor la depreciacion del periodo.
     *
     * El calculo vive en el modelo y no se reimplementa aqui: repetir la
     * formula en javascript es la forma facil de que las dos copias
     * acaben dando numeros distintos.
     */
    function consultarDepreciacion() {
        limpiarAviso();

        const elEquipo = equipo.val();
        const inicio = fechaInicio.val();

        if (!elEquipo || !inicio) {
            limpiarPrevisualizacion();
            return;
        }

        dias.val('…');

        $.ajax({
            url: $formulario.data('depreciacion-url'),
            type: 'GET',
            data: {
                equipo_id: elEquipo,
                fecha_inicio: inicio,
                fecha_fin: fechaFin.val() || ''
            },
            success: function (response) {
                dias.val(numero(response.dias, 2));
                tasaDiaria.val(numero(response.depreciacion_diaria, 4));
                depreciacion.val(numero(response.depreciacion_total, 2));

                codigoEquipo.val(
                    (response.codigo ?? '—')
                    + ' · '
                    + numero(response.valor_adquisicion, 2)
                );
                vidaUtil.text((response.vida_util_meses ?? '—') + ' meses');

                if (response.solapamiento) {
                    mostrarAviso(response.solapamiento, 'danger');
                } else if (response.disponible === false) {
                    mostrarAviso(
                        'Este equipo ya está totalmente depreciado: no le queda valor por consumir, ' +
                        'así que el costo de depreciación de este periodo es cero.',
                        'warning'
                    );
                }
            },
            error: function () {
                limpiarPrevisualizacion();
            }
        });
    }

    function limpiarPrevisualizacion() {
        dias.val('—');
        tasaDiaria.val('—');
        depreciacion.val('—');
        codigoEquipo.val('—');
        vidaUtil.text('—');
    }

    function limpiarFormulario() {
        $formulario[0]?.reset();

        equipo.val('');
        sincronizarSelect2(equipo);

        sigueAsignado.prop('checked', false);
        fechaFin.prop('disabled', false);

        limpiarPrevisualizacion();
        limpiarAviso();
        limpiarError();
    }

    function limpiarModalShow() {
        $('#showUsoEquipo').val('');
        $('#showUsoCodigo').val('');
        $('#showUsoProceso').val('');
        $('#showUsoDias').val('');
        $('#showUsoInicio').val('');
        $('#showUsoFin').val('');
        $('#showUsoTasa').val('');
        $('#showUsoDepreciacion').val('');
        $('#showUsoObservaciones').val('');
    }

    function mostrarError(mensaje) {
        error.removeClass('d-none').text(mensaje);
    }

    function limpiarError() {
        error.addClass('d-none').text('');
    }

    function mostrarAviso(mensaje, tipo) {
        aviso
            .removeClass('d-none alert-danger alert-warning alert-info')
            .addClass('alert-' + tipo)
            .text(mensaje);
    }

    function limpiarAviso() {
        aviso.addClass('d-none').text('');
    }

    function numero(valor, decimales) {
        const n = parseFloat(valor);

        if (isNaN(n)) {
            return '—';
        }

        return n.toLocaleString('es-NI', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        });
    }

    /**
     * "2026-10-01T08:00" a "01/10/2026 08:00", para que se lea igual que
     * el resto de las fechas de la pantalla.
     */
    function formatearFechaHora(valor) {
        if (!valor) {
            return '';
        }

        const fecha = new Date(valor);

        if (isNaN(fecha.getTime())) {
            return valor;
        }

        const dosCifras = (n) => String(n).padStart(2, '0');

        return dosCifras(fecha.getDate()) + '/'
            + dosCifras(fecha.getMonth() + 1) + '/'
            + fecha.getFullYear() + ' '
            + dosCifras(fecha.getHours()) + ':'
            + dosCifras(fecha.getMinutes());
    }

    $formulario.on('submit', function (e) {
        if (sigueAsignado.prop('checked')) {
            fechaFin.val('');
        }

        $('#btnGuardarUsoEquipo').prop('disabled', true);
    });
});
