(function ($) {
    'use strict';

    $(document).on('click', '.btn-ver-empleado-pago', function () {
        const button = $(this);
        const modalElement = document.getElementById('modalEmpleadoPago');
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

        const url = button.data('url');
        const editUrl = button.data('edit-url');

        $('#empleadoPagoContenido').addClass('d-none');
        $('#empleadoPagoError').addClass('d-none');
        $('#empleadoPagoLoading').removeClass('d-none');
        $('#btnEditarEmpleadoPago').attr('href', editUrl);

        modal.show();

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                $('#empleadoPagoEmpleado').text(
                    `${response.empleado.nombre} · ${response.empleado.codigo}`
                );

                $('#empleadoPagoTipo').text(response.tipo_pago ?? '—');
                $('#empleadoPagoTarifa').text(response.tarifa ?? '—');
                $('#empleadoPagoMoneda').text(response.moneda ?? '—');
                $('#empleadoPagoFechaInicio').text(response.fecha_inicio ?? '—');
                $('#empleadoPagoFechaFin').text(response.fecha_fin ?? 'Vigente');

                $('#empleadoPagoEstado').html(
                    response.estado
                        ? '<span class="badge bg-success">Activo</span>'
                        : '<span class="badge bg-danger">Inactivo</span>'
                );

                $('#empleadoPagoObservaciones').text(
                    response.observaciones || 'Sin observaciones.'
                );

                $('#empleadoPagoCreado').text(response.created_at ?? '—');
                $('#empleadoPagoActualizado').text(response.updated_at ?? '—');

                $('#empleadoPagoLoading').addClass('d-none');
                $('#empleadoPagoContenido').removeClass('d-none');
            },
            error: function (xhr) {
                console.error('Error cargando tarifa:', xhr);

                $('#empleadoPagoLoading').addClass('d-none');
                $('#empleadoPagoError').removeClass('d-none');
            }
        });
    });

})(jQuery);
