(function ($) {
    'use strict';
    $(function () {
        const $form = $('#formUnidad');
        if (!$form.length) return;
        const modal = new bootstrap.Modal(document.getElementById('modalUnidad'));
        function recargar() { $('#unidades-medida-table').DataTable().ajax.reload(); }
        function limpiar() {
            $form[0].reset();
            $form.attr('action', $form.data('store-url')).find('input[name="_method"]').remove();
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('').hide();
            $('#unidadEstado').prop('checked', true); $('#unidadEstadoValor').val('1');
            $('#unidadError').addClass('d-none').empty();
        }
        function error(xhr) {
            const errs = xhr.responseJSON?.errors || {};
            const $name = $form.find('[name="nombre"]');
            if (errs.nombre) { $name.addClass('is-invalid').siblings('.invalid-feedback').text(errs.nombre[0]).show(); }
            else $('#unidadError').removeClass('d-none').text(Object.values(errs).flat().join(' ') || xhr.responseJSON?.message || 'No se pudo guardar la unidad.');
        }
        $('#unidadEstado').on('change', function () { $('#unidadEstadoValor').val(this.checked ? '1' : '0'); });
        $('#btnNuevaUnidad').on('click', function () { limpiar(); $('#modalUnidadTitulo').text('Nueva unidad'); $('#btnGuardarUnidad').text('Guardar unidad'); modal.show(); });
        $(document).on('click', '.btn-editar-unidad', function () {
            $.get($(this).data('url')).done(function (data) {
                limpiar(); $('#modalUnidadTitulo').text('Editar unidad'); $('#btnGuardarUnidad').text('Guardar cambios');
                $form.attr('action', $form.data('update-url').replace('__ID__', data.id)).append($('<input>', {type: 'hidden', name: '_method', value: 'PUT'}));
                $('#unidadNombre').val(data.nombre); $('#unidadEstado').prop('checked', !!data.estado); $('#unidadEstadoValor').val(data.estado ? '1' : '0'); modal.show();
            }).fail(error);
        });
        $form.on('submit', function (event) {
            event.preventDefault(); const $button = $('#btnGuardarUnidad').prop('disabled', true);
            $.post($form.attr('action'), $form.serialize()).done(function () { modal.hide(); recargar(); }).fail(error).always(function () { $button.prop('disabled', false); });
        });
        $(document).on('click', '.btn-borrar-unidad', function () {
            const $button = $(this);
            Swal.fire({title: '¿Eliminar «' + $button.data('nombre') + '»?', text: 'Solo se pueden eliminar unidades que no estén en uso.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar'})
                .then(function (result) { if (!result.isConfirmed) return;
                    $.post($button.data('url'), {_method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content')}).done(recargar).fail(function (xhr) {
                        Swal.fire({title: 'No se puede eliminar', text: Object.values(xhr.responseJSON?.errors || {}).flat().join(' ') || xhr.responseJSON?.message || 'Error al eliminar.', icon: 'warning'});
                    });
                });
        });
    });
})(jQuery);
