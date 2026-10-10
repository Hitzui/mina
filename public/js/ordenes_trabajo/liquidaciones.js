(function ($) {
    'use strict';

    $(function () {
        const $form = $('#formLiquidacionOrden');
        if (!$form.length) return;

        const modal = new bootstrap.Modal(document.getElementById('modalLiquidacionOrden'));
        let porcentajesFijos = null;

        function recargar() {
            if ($.fn.dataTable.isDataTable('#liquidaciones-orden-table')) {
                $('#liquidaciones-orden-table').DataTable().ajax.reload();
            }
        }

        function ponerFecha(fecha) {
            const campo = $('#liquidacionFecha')[0];
            if (campo?._flatpickr) {
                campo._flatpickr.setDate(fecha || null, true);
            } else {
                $('#liquidacionFecha').val(fecha || '');
            }
        }

        function calcularPreview() {
            const $option = $('#liquidacionAcuerdo option:selected');
            const cliente = porcentajesFijos
                ? porcentajesFijos.cliente
                : parseFloat($option.data('cliente'));
            const empresa = porcentajesFijos
                ? porcentajesFijos.empresa
                : parseFloat($option.data('empresa'));
            const gramos = parseFloat($('#liquidacionGramos').val());

            if (!Number.isFinite(cliente) || !Number.isFinite(empresa)) {
                $('#liquidacionPorcentajesPreview').text('Seleccione un acuerdo porcentual.');
                $('#liquidacionGramosPreview').empty();
                return;
            }

            $('#liquidacionPorcentajesPreview').text(
                cliente.toFixed(4) + '% para el cliente / ' + empresa.toFixed(4) + '% para la empresa'
            );

            if (!Number.isFinite(gramos) || gramos <= 0) {
                $('#liquidacionGramosPreview').empty();
                return;
            }

            const gramosCliente = Math.round(gramos * cliente / 100 * 10000) / 10000;
            const gramosEmpresa = Math.round((gramos - gramosCliente) * 10000) / 10000;
            $('#liquidacionGramosPreview').text(
                gramosCliente.toFixed(4) + ' g cliente + ' + gramosEmpresa.toFixed(4) + ' g empresa'
            );
        }

        function limpiar() {
            $form[0].reset();
            $form.attr('action', $form.data('store-url'));
            $form.find('input[name="_method"]').remove();
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('').hide();
            $('#liquidacionOrdenError').addClass('d-none').empty();
            $('#liquidacionGramos, #liquidacionObservaciones').val('');
            $('#liquidacionAcuerdo').val('').prop('disabled', false).trigger('change');
            $('.acuerdo-liquidacion-selector').removeClass('d-none');
            $('#liquidacionPorcentajesHistoricos').addClass('d-none').empty();
            porcentajesFijos = null;
            ponerFecha($('#liquidacionFecha').attr('value') || '');
            calcularPreview();
        }

        function mostrarErrores(xhr) {
            const errores = xhr.responseJSON?.errors || {};
            let mostrados = 0;
            Object.entries(errores).forEach(function ([campo, mensajes]) {
                const $campo = $form.find('[name="' + campo + '"]');
                if (!$campo.length) {
                    $('#liquidacionOrdenError').removeClass('d-none').text(mensajes[0]);
                    mostrados++;
                    return;
                }
                $campo.addClass('is-invalid');
                $campo.siblings('.invalid-feedback').first().text(mensajes[0]).show();
                mostrados++;
            });
            if (!mostrados) {
                $('#liquidacionOrdenError').removeClass('d-none').text(
                    xhr.responseJSON?.message || 'No se pudo guardar la liquidación.'
                );
            }
        }

        $('#liquidacionAcuerdo').on('change', calcularPreview);
        $('#liquidacionGramos').on('input', calcularPreview);

        $('#btnNuevaLiquidacion').on('click', function () {
            limpiar();
            porcentajesFijos = null;
            $('#modalLiquidacionOrdenLabel').text('Nueva liquidación');
            $('#btnGuardarLiquidacion').text('Guardar liquidación');
            modal.show();
        });

        $(document).on('click', '.btn-editar-liquidacion', function () {
            $.get($(this).data('url')).done(function (datos) {
                limpiar();
                porcentajesFijos = {
                    cliente: parseFloat(datos.porcentaje_cliente),
                    empresa: parseFloat(datos.porcentaje_empresa),
                };
                $('.acuerdo-liquidacion-selector').addClass('d-none');
                $('#liquidacionAcuerdo').prop('disabled', true);
                $('#liquidacionPorcentajesHistoricos')
                    .removeClass('d-none')
                    .text('Porcentajes históricos conservados en esta liquidación.');
                ponerFecha(datos.fecha);
                $('#liquidacionGramos').val(datos.gramos_recuperados);
                $('#liquidacionObservaciones').val(datos.observaciones || '');
                $('#modalLiquidacionOrdenLabel').text('Corregir liquidación');
                $('#btnGuardarLiquidacion').text('Guardar corrección');
                $form.attr('action', $form.data('update-url').replace('__ID__', datos.id));
                $form.append($('<input>', {type: 'hidden', name: '_method', value: 'PUT'}));
                calcularPreview();
                modal.show();
            }).fail(function (xhr) {
                window.Swal.fire({
                    title: 'No se pudo abrir la liquidación',
                    text: xhr.responseJSON?.message || 'Intente de nuevo.',
                    icon: 'error',
                });
            });
        });

        $form.on('submit', function (event) {
            event.preventDefault();
            const $button = $('#btnGuardarLiquidacion').prop('disabled', true);
            $.ajax({url: $form.attr('action'), method: 'POST', data: $form.serialize()})
                .done(function () { modal.hide(); recargar(); })
                .fail(mostrarErrores)
                .always(function () { $button.prop('disabled', false); });
        });

        $(document).on('click', '.btn-borrar-liquidacion', function () {
            const $button = $(this);
            window.Swal.fire({
                title: '¿Eliminar esta liquidación?',
                text: 'Se eliminará la liquidación del ' + ($button.data('fecha') || '') + '.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
            }).then(function (resultado) {
                if (!resultado.isConfirmed) return;
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
