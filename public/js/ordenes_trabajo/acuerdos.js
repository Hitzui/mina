(function ($) {
    'use strict';

    $(function () {
        const $form = $('#formAcuerdoOrden');
        if (!$form.length) return;

        const modal = new bootstrap.Modal(document.getElementById('modalAcuerdoOrden'));

        function recargar() {
            if ($.fn.dataTable.isDataTable('#acuerdos-orden-table')) {
                $('#acuerdos-orden-table').DataTable().ajax.reload();
            }
        }

        function limpiar() {
            $form[0].reset();
            $form.attr('action', $form.data('store-url'));
            $form.find('input[name="_method"]').remove();
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('').hide();
            $('#acuerdoOrdenError').addClass('d-none').empty();
            $('#acuerdoModalidadPorcentaje').prop('checked', true);
            $('#acuerdoEstado').prop('checked', true);
            $('#acuerdoEstadoValor').val('1');
            $('#acuerdoTipo, #acuerdoMoneda').val('').trigger('change');
            $('#acuerdoPorcentajeCliente, #acuerdoPorcentajeEmpresa, #acuerdoTarifa, #acuerdoObservaciones').val('');
            cambiarModalidad();
        }

        function cambiarModalidad() {
            const tarifa = $('#acuerdoModalidadTarifa').is(':checked');
            $('.campos-tarifa-acuerdo').toggleClass('d-none', !tarifa);
            $('.campos-porcentaje-acuerdo').toggleClass('d-none', tarifa);
            $('#acuerdoMoneda').prop('disabled', !tarifa).prop('required', tarifa);
            $('#acuerdoMonedaBase').prop('disabled', tarifa);
            if (tarifa) {
                $('#acuerdoPorcentajeCliente, #acuerdoPorcentajeEmpresa').val('');
            } else {
                $('#acuerdoTarifa').val('');
            }
        }

        function ponerOpcionHistorica($select, id, texto) {
            if (!id || $select.find('option[value="' + id + '"]').length) return;
            $select.append($('<option>', {value: id, text: texto + ' (inactivo)'}));
        }

        function editar(url) {
            $.get(url).done(function (datos) {
                limpiar();
                ponerOpcionHistorica($('#acuerdoTipo'), datos.tipo_participacion_id, datos.tipo_participacion || 'Tipo histórico');
                ponerOpcionHistorica($('#acuerdoMoneda'), datos.moneda_id, datos.moneda || 'Moneda histórica');
                const esTarifa = datos.tarifa_servicio !== null && datos.tarifa_servicio !== '';
                $('#acuerdoModalidadTarifa').prop('checked', esTarifa);
                $('#acuerdoModalidadPorcentaje').prop('checked', !esTarifa);
                cambiarModalidad();
                $('#acuerdoTipo').val(datos.tipo_participacion_id).trigger('change');
                $('#acuerdoMoneda').val(datos.moneda_id).trigger('change');
                $('#acuerdoPorcentajeCliente').val(datos.porcentaje_cliente ?? '');
                $('#acuerdoPorcentajeEmpresa').val(datos.porcentaje_empresa ?? '');
                $('#acuerdoTarifa').val(datos.tarifa_servicio ?? '');
                $('#acuerdoObservaciones').val(datos.observaciones || '');
                $('#acuerdoLamaDestino').val(datos.lama_destino || 'sin_definir');
                $('#acuerdoLamaEmpresa').val(datos.lama_porcentaje_empresa ?? '');
                $('#acuerdoEstado').prop('checked', !!datos.estado);
                $('#acuerdoEstadoValor').val(datos.estado ? '1' : '0');
                $('#modalAcuerdoOrdenLabel').text('Editar acuerdo de participación');
                $('#btnGuardarAcuerdoOrden').text('Guardar cambios');
                $form.attr('action', $form.data('update-url').replace('__ID__', datos.id));
                $form.append($('<input>', {type: 'hidden', name: '_method', value: 'PUT'}));
                modal.show();
            }).fail(function (xhr) {
                window.Swal.fire({
                    title: 'No se pudo abrir el acuerdo',
                    text: xhr.responseJSON?.message || 'Intente de nuevo.',
                    icon: 'error',
                });
            });
        }

        function mostrarErrores(xhr) {
            const errores = xhr.responseJSON?.errors || {};
            let mostrados = 0;
            Object.entries(errores).forEach(function ([campo, mensajes]) {
                if (campo === 'modalidad') {
                    $('#acuerdoOrdenError').removeClass('d-none').text(mensajes[0]);
                    mostrados++;
                    return;
                }
                const $campo = $form.find('[name="' + campo + '"]');
                if (!$campo.length) return;
                if (campo.startsWith('lama_')) {
                    $('#acuerdoOrdenError').removeClass('d-none').text(mensajes[0]);
                }
                $campo.addClass('is-invalid');
                const $feedback = $campo.closest('.input-group').length
                    ? $campo.closest('.input-group').next('.invalid-feedback')
                    : $campo.siblings('.invalid-feedback').first();
                $feedback.text(mensajes[0]).show();
                mostrados++;
            });
            if (!mostrados) {
                $('#acuerdoOrdenError').removeClass('d-none').text(
                    xhr.responseJSON?.message || 'No se pudo guardar el acuerdo.'
                );
            }
        }

        $('#acuerdoEstado').on('change', function () {
            $('#acuerdoEstadoValor').val(this.checked ? '1' : '0');
        });
        $('#acuerdoModalidadPorcentaje, #acuerdoModalidadTarifa').on('change', cambiarModalidad);

        $('#btnNuevoAcuerdoOrden').on('click', function () {
            limpiar();
            $('#modalAcuerdoOrdenLabel').text('Nuevo acuerdo de participación');
            $('#btnGuardarAcuerdoOrden').text('Guardar acuerdo');
            modal.show();
        });

        $(document).on('click', '.btn-editar-acuerdo-orden', function () {
            editar($(this).data('url'));
        });

        $form.on('submit', function (event) {
            event.preventDefault();
            const $button = $('#btnGuardarAcuerdoOrden').prop('disabled', true);
            $.ajax({url: $form.attr('action'), method: 'POST', data: $form.serialize()})
                .done(function () { modal.hide(); recargar(); })
                .fail(mostrarErrores)
                .always(function () { $button.prop('disabled', false); });
        });

        $(document).on('click', '.btn-borrar-acuerdo-orden', function () {
            const $button = $(this);
            window.Swal.fire({
                title: '¿Eliminar este acuerdo?',
                text: 'Se eliminará el acuerdo «' + ($button.data('tipo') || '') + '» de esta orden.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
            }).then(function (result) {
                if (!result.isConfirmed) return;
                $.ajax({url: $button.data('url'), method: 'POST', data: {
                    _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content'),
                }}).done(recargar).fail(function (xhr) {
                    window.Swal.fire({
                        title: 'No se pudo eliminar',
                        text: xhr.responseJSON?.message || 'Intente de nuevo.',
                        icon: 'error',
                    });
                });
            });
        });
    });
})(jQuery);
