(function ($) {
    'use strict';

    $(function () {
        const $form = $('#formTipoParticipacion');
        if (!$form.length) return;

        const modalForm = new bootstrap.Modal(document.getElementById('modalTipoParticipacion'));
        const modalFicha = new bootstrap.Modal(document.getElementById('modalVerTipoParticipacion'));
        let ficha = null;

        function recargar() {
            if ($.fn.dataTable.isDataTable('#tipos-participacion-table')) {
                $('#tipos-participacion-table').DataTable().ajax.reload();
            } else {
                window.location.reload();
            }
        }

        function limpiar() {
            $form[0].reset();
            $form.attr('action', $form.data('store-url'));
            $form.find('input[name="_method"]').remove();
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('').hide();
            $('#tipoParticipacionNombre, #tipoParticipacionDescripcion').val('');
            $('#tipoParticipacionEstado').prop('checked', true);
            $('#tipoParticipacionEstadoValor').val('1');
            $('#tipoParticipacionError').addClass('d-none').empty();
        }

        function mostrarError(xhr) {
            const errores = xhr.responseJSON?.errors || {};
            let marcados = 0;
            Object.entries(errores).forEach(function ([campo, mensajes]) {
                const $campo = $form.find('[name="' + campo + '"]');
                if (!$campo.length) return;
                $campo.addClass('is-invalid');
                $campo.siblings('.invalid-feedback').first().text(mensajes[0]).show();
                marcados++;
            });
            if (!marcados) {
                $('#tipoParticipacionError').removeClass('d-none').text(
                    xhr.responseJSON?.message || Object.values(errores).flat().join(' ') || 'No se pudo guardar el tipo de participación.'
                );
            }
        }

        function editarDesde(url) {
            $.get(url).done(function (datos) {
                limpiar();
                $('#modalTipoParticipacionLabel').text('Editar tipo de participación');
                $('#btnGuardarTipoParticipacion').text('Guardar cambios');
                $form.attr('action', $form.data('update-url').replace('__ID__', datos.id));
                $form.append($('<input>', {type: 'hidden', name: '_method', value: 'PUT'}));
                $('#tipoParticipacionNombre').val(datos.nombre || '');
                $('#tipoParticipacionDescripcion').val(datos.descripcion || '');
                $('#tipoParticipacionEstado').prop('checked', !!datos.estado);
                $('#tipoParticipacionEstadoValor').val(datos.estado ? '1' : '0');
                modalFicha.hide();
                modalForm.show();
            }).fail(mostrarError);
        }

        $('#tipoParticipacionEstado').on('change', function () {
            $('#tipoParticipacionEstadoValor').val(this.checked ? '1' : '0');
        });

        $('#btnNuevoTipoParticipacion').on('click', function () {
            limpiar();
            $('#modalTipoParticipacionLabel').text('Nuevo tipo de participación');
            $('#btnGuardarTipoParticipacion').text('Guardar tipo');
            modalForm.show();
        });

        $(document).on('click', '.btn-edit-tipo-participacion', function () {
            editarDesde($(this).data('url'));
        });

        $(document).on('click', '.btn-ver-tipo-participacion', function () {
            $.get($(this).data('url')).done(function (datos) {
                ficha = datos;
                $('#fichaTipoParticipacionNombre').text(datos.nombre || '—');
                $('#fichaTipoParticipacionDescripcion').text(datos.descripcion || '—');
                $('#fichaTipoParticipacionEstado').html(datos.estado
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-secondary">Inactivo</span>');
                $('#fichaTipoParticipacionUsos').text(datos.usos?.length
                    ? 'En ' + datos.frase_de_usos + '. No se puede borrar; puede desactivarlo.'
                    : 'No tiene acuerdos asociados. Se puede borrar.');
                modalFicha.show();
            }).fail(function () {
                $('#tipoParticipacionFichaError').removeClass('d-none').text('No se pudo cargar la ficha.');
                modalFicha.show();
            });
        });

        $('#btnEditarTipoParticipacion').on('click', function () {
            if (ficha?.url_editar) editarDesde(ficha.url_editar);
        });

        $form.on('submit', function (event) {
            event.preventDefault();
            const $button = $('#btnGuardarTipoParticipacion').prop('disabled', true);
            $.ajax({url: $form.attr('action'), method: 'POST', data: $form.serialize()})
                .done(function () { modalForm.hide(); recargar(); })
                .fail(mostrarError)
                .always(function () { $button.prop('disabled', false); });
        });

        $(document).on('click', '.btn-cambiar-estado-tipo-participacion', function () {
            const $button = $(this).prop('disabled', true);
            $.post($button.data('url'), {_token: $('meta[name="csrf-token"]').attr('content')})
                .done(recargar).always(function () { $button.prop('disabled', false); });
        });

        $(document).on('click', '.btn-borrar-tipo-participacion', function () {
            const $button = $(this);
            Swal.fire({
                title: '¿Eliminar «' + ($button.data('nombre') || '') + '»?',
                text: 'Solo se puede si ningún acuerdo conserva este tipo. Si está usado, desactívelo.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
            }).then(function (result) {
                if (!result.isConfirmed) return;
                $.ajax({url: $button.data('url'), method: 'POST', data: {
                    _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content'),
                }}).done(recargar).fail(function (xhr) {
                    const mensaje = Object.values(xhr.responseJSON?.errors || {}).flat().join(' ')
                        || xhr.responseJSON?.message || 'No se pudo eliminar el tipo.';
                    Swal.fire({title: 'No se puede eliminar', text: mensaje, icon: 'warning'});
                });
            });
        });
    });
})(jQuery);
