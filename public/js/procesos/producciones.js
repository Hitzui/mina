(function ($) {
    'use strict';

    $(function () {
        const $form = $('#formProduccion');
        if (!$form.length) return;

        const modal = new bootstrap.Modal(document.getElementById('modalProduccion'));
        const $orden = $('#produccionOrden');
        const $proceso = $('#produccionProceso');
        const $tipo = $('#produccionTipo');
        const $unidad = $('#produccionUnidad');
        const $error = $('#produccionError');

        function setValue($select, value) {
            $select.val(value ?? '');
            if ($.fn.select2 && $select.data('select2')) $select.trigger('change.select2');
        }

        function cargarProcesos(ordenId, selectedId) {
            $proceso.empty().append(new Option('Producción de la orden completa', ''));
            $proceso.prop('disabled', !ordenId);
            if (!ordenId) {
                setValue($proceso, '');
                return $.Deferred().resolve().promise();
            }

            return $.get($form.data('procesos-url'), {orden_trabajo_id: ordenId}).done(function (procesos) {
                procesos.forEach(function (proceso) {
                    const label = proceso.nombre + ' — ' + proceso.estado;
                    const option = new Option(label, proceso.id, false, String(proceso.id) === String(selectedId));
                    option.dataset.cerrado = proceso.cerrado ? '1' : '0';
                    option.disabled = proceso.cerrado && String(proceso.id) !== String(selectedId);
                    $proceso.append(option);
                });
                setValue($proceso, selectedId || '');
                $proceso.prop('disabled', false);
            });
        }

        function limpiarFormulario() {
            $form[0].reset();
            $tipo.find('option[data-temporal="1"]').remove();
            $unidad.find('option[data-temporal="1"]').remove();
            $form.attr('action', $form.data('store-url'));
            $form.find('input[name="_method"]').remove();
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('').hide();
            $error.addClass('d-none').empty();

            setValue($orden, '');
            setValue($tipo, '');
            cargarProcesos('', '');
            setValue($unidad, '');
            $('#produccionFecha, #produccionCantidad, #produccionDescripcion, #produccionObservaciones').val('');
        }

        function mostrarError(xhr) {
            const errors = xhr.responseJSON?.errors || {};
            let mostrados = 0;
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('').hide();

            Object.entries(errors).forEach(function ([name, messages]) {
                const $field = $form.find('[name="' + name + '"]');
                if (!$field.length) return;
                $field.addClass('is-invalid');
                let $feedback = $field.siblings('.invalid-feedback').first();
                if (!$feedback.length) {
                    $feedback = $field.closest('.row > div').find('.invalid-feedback').first();
                }
                $feedback.text(messages[0]).show();
                mostrados++;
            });

            if (!mostrados) {
                $error.removeClass('d-none').text(
                    xhr.responseJSON?.message || Object.values(errors).flat().join(' ') || 'No se pudo guardar la producción.'
                );
            }
        }

        function recargarTabla() {
            if ($.fn.dataTable.isDataTable('#producciones-table')) {
                $('#producciones-table').DataTable().ajax.reload();
            } else {
                window.location.reload();
            }
        }

        function agregarOpcionTipo(id, nombre) {
            if (!id || !nombre || $tipo.find('option[value="' + id + '"]').length) return;
            const option = new Option(nombre + ' (inactivo)', id);
            option.dataset.temporal = '1';
            $tipo.append(option);
        }

        function agregarOpcionUnidad(id, nombre) {
            if (!id || $unidad.find('option').filter(function () {
                return this.value === String(id);
            }).length) return;
            const option = new Option(nombre || id, id);
            option.dataset.temporal = '1';
            $unidad.append(option);
        }

        $(document).on('click', '#btnNuevaProduccion', function () {
            limpiarFormulario();
            $('#modalProduccionLabel').text('Nueva producción');
            $('#btnGuardarProduccion').text('Guardar producción');
            modal.show();
        });

        $orden.on('change', function () {
            cargarProcesos(this.value, '');
        });

        $(document).on('click', '.btn-edit-produccion', function () {
            $.get($(this).data('url')).done(function (data) {
                limpiarFormulario();
                $('#modalProduccionLabel').text('Editar producción');
                $('#btnGuardarProduccion').text('Guardar cambios');
                $form.attr('action', $form.data('update-url').replace('__ID__', data.id));
                $form.append($('<input>', {type: 'hidden', name: '_method', value: 'PUT'}));

                agregarOpcionTipo(data.tipo_produccion_id, data.tipo_produccion_nombre);
                agregarOpcionUnidad(data.unidad_medida_id, data.unidad_medida_nombre);
                setValue($orden, data.orden_trabajo_id);
                setValue($tipo, data.tipo_produccion_id);
                setValue($unidad, data.unidad_medida_id);
                $('#produccionFecha').val(data.fecha || '');
                $('#produccionCantidad').val(data.cantidad ?? '');
                $('#produccionDescripcion').val(data.descripcion || '');
                $('#produccionObservaciones').val(data.observaciones || '');

                cargarProcesos(data.orden_trabajo_id, data.proceso_orden_id);
                modal.show();
            }).fail(mostrarError);
        });

        $form.on('submit', function (event) {
            event.preventDefault();
            $error.addClass('d-none').empty();
            const $button = $('#btnGuardarProduccion').prop('disabled', true);

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).done(function () {
                modal.hide();
                recargarTabla();
            }).fail(mostrarError).always(function () {
                $button.prop('disabled', false);
            });
        });

        $(document).on('click', '.btn-delete-produccion', function () {
            const $button = $(this);
            Swal.fire({
                title: '¿Eliminar esta producción?',
                text: 'Se eliminará ' + $button.data('tipo') + ': '
                    + $button.data('cantidad') + ' del ' + $button.data('fecha') + '.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
            }).then(function (result) {
                if (!result.isConfirmed) return;
                $button.prop('disabled', true);
                $.ajax({
                    url: $button.data('url'),
                    method: 'POST',
                    data: {_method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content')},
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                }).done(recargarTabla).fail(function (xhr) {
                    $button.prop('disabled', false);
                    Swal.fire({
                        title: 'No se pudo eliminar',
                        text: xhr.responseJSON?.message || 'Intente de nuevo.',
                        icon: 'error',
                    });
                });
            });
        });
    });
})(jQuery);
