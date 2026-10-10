(function ($) {
    'use strict';

    $(function () {
        const $form = $('#formTipoProduccion');
        if (!$form.length) return;

        const modalForm = new bootstrap.Modal(document.getElementById('modalTipoProduccion'));
        const modalFicha = new bootstrap.Modal(document.getElementById('modalVerTipoProduccion'));
        let ficha = null;

        function recargar() {
            if ($.fn.dataTable.isDataTable('#tipos-produccion-table')) {
                $('#tipos-produccion-table').DataTable().ajax.reload();
            } else {
                window.location.reload();
            }
        }

        function resetForm() {
            $form[0].reset();
            $form.attr('action', $form.data('store-url'));
            $form.find('input[name="_method"]').remove();
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('').hide();
            $('#tipoProduccionNombre, #tipoProduccionDescripcion').val('');
            $('#tipoProduccionEstado').prop('checked', true);
            $('#tipoProduccionEstadoValor').val('1');
            $('#tipoProduccionError').addClass('d-none').empty();
        }

        function showError(xhr) {
            const errors = xhr.responseJSON?.errors || {};
            let shown = 0;
            Object.entries(errors).forEach(([key, messages]) => {
                const $field = $form.find('[name="' + key + '"]');
                if (!$field.length) return;
                $field.addClass('is-invalid');
                $field.siblings('.invalid-feedback').first().text(messages[0]).show();
                shown++;
            });
            if (!shown) {
                $('#tipoProduccionError').removeClass('d-none').text(
                    xhr.responseJSON?.message || Object.values(errors).flat().join(' ') || 'No se pudo guardar el tipo de producción.'
                );
            }
        }

        function editFrom(url) {
            $.get(url).done(function (data) {
                resetForm();
                $('#modalTipoProduccionLabel').text('Editar tipo de producción');
                $('#btnGuardarTipoProduccion').text('Guardar cambios');
                $form.attr('action', $form.data('update-url').replace('__ID__', data.id));
                $form.append($('<input>', {type: 'hidden', name: '_method', value: 'PUT'}));
                $('#tipoProduccionNombre').val(data.nombre || '');
                $('#tipoProduccionDescripcion').val(data.descripcion || '');
                $('#tipoProduccionEstado').prop('checked', !!data.estado);
                $('#tipoProduccionEstadoValor').val(data.estado ? '1' : '0');
                modalFicha.hide();
                modalForm.show();
            }).fail(showError);
        }

        $('#tipoProduccionEstado').on('change', function () {
            $('#tipoProduccionEstadoValor').val(this.checked ? '1' : '0');
        });

        $('#btnNuevoTipoProduccion').on('click', function () {
            resetForm();
            $('#modalTipoProduccionLabel').text('Nuevo tipo de producción');
            $('#btnGuardarTipoProduccion').text('Guardar tipo');
            modalForm.show();
        });

        $(document).on('click', '.btn-edit-tipo-produccion', function () {
            editFrom($(this).data('url'));
        });

        $(document).on('click', '.btn-ver-tipo-produccion', function () {
            $.get($(this).data('url')).done(function (data) {
                ficha = data;
                $('#fichaTipoProduccionNombre').text(data.nombre || '—');
                $('#fichaTipoProduccionDescripcion').text(data.descripcion || '—');
                $('#fichaTipoProduccionEstado').html(data.estado
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-secondary">Inactivo</span>');
                $('#fichaTipoProduccionUsos').text(data.usos?.length
                    ? 'En ' + data.frase_de_usos + '. No se puede borrar; puede desactivarlo.'
                    : 'No tiene producciones asociadas. Se puede borrar.');
                modalFicha.show();
            }).fail(function () {
                $('#tipoProduccionFichaError').removeClass('d-none').text('No se pudo cargar la ficha.');
                modalFicha.show();
            });
        });

        $('#btnEditarTipoProduccion').on('click', function () {
            if (ficha?.url_editar) editFrom(ficha.url_editar);
        });

        $form.on('submit', function (event) {
            event.preventDefault();
            const $button = $('#btnGuardarTipoProduccion').prop('disabled', true);
            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            }).done(function () {
                modalForm.hide();
                recargar();
            }).fail(showError).always(function () {
                $button.prop('disabled', false);
            });
        });

        $(document).on('click', '.btn-cambiar-estado-tipo-produccion', function () {
            const $button = $(this).prop('disabled', true);
            $.post($button.data('url'), {_token: $('meta[name="csrf-token"]').attr('content')})
                .done(recargar)
                .always(function () { $button.prop('disabled', false); });
        });

        $(document).on('click', '.btn-borrar-tipo-produccion', function () {
            const $button = $(this);
            Swal.fire({
                title: '¿Eliminar "' + ($button.data('nombre') || '') + '"?',
                text: 'Solo se puede si ninguna producción conserva este tipo. Si está usado, desactívelo.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
            }).then(function (result) {
                if (!result.isConfirmed) return;
                $.ajax({url: $button.data('url'), method: 'POST', data: {
                    _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content'),
                }}).done(recargar).fail(function (xhr) {
                    const msg = Object.values(xhr.responseJSON?.errors || {}).flat().join(' ')
                        || xhr.responseJSON?.message || 'No se pudo eliminar el tipo.';
                    Swal.fire({title: 'No se puede eliminar', text: msg, icon: 'warning'});
                });
            });
        });
    });
})(jQuery);
