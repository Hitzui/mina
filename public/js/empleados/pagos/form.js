(function ($) {
    'use strict';

    const modalElement = document.getElementById('modalEmpleadoPagoForm');

    if (!modalElement) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

    function cargarFormulario(url, titulo) {
        $('#empleadoPagoFormTitulo').text(titulo);
        $('#empleadoPagoFormContenido').addClass('d-none').empty();
        $('#empleadoPagoFormError').addClass('d-none');
        $('#empleadoPagoFormLoading').removeClass('d-none');
        modal.show();

        $.get(url)
            .done(function (html) {
                $('#empleadoPagoFormLoading').addClass('d-none');
                $('#empleadoPagoFormContenido').html(html).removeClass('d-none');
                inicializarFormulario();
            })
            .fail(function (xhr) {
                console.error('Error cargando formulario:', xhr);
                $('#empleadoPagoFormLoading').addClass('d-none');
                $('#empleadoPagoFormError').removeClass('d-none');
            });
    }

    function inicializarFormulario() {
        const config = {
            locale: 'es',
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            allowInput: false
        };

        const fechaInicio = document.querySelector('#empleadoPagoFormContenido #fecha_inicio');
        const fechaFin = document.querySelector('#empleadoPagoFormContenido #fecha_fin');

        if (fechaInicio) flatpickr(fechaInicio, config);
        if (fechaFin) flatpickr(fechaFin, config);
    }

    $(document).on('click', '#btnNuevaTarifa', function () {
        cargarFormulario($(this).data('url'), 'Nueva tarifa');
    });

    $(document).on('click', '.btn-editar-empleado-pago', function () {
        cargarFormulario($(this).data('url'), 'Editar tarifa');
    });

    $(document).on('submit', '#empleadoPagoFormContenido form', function () {
        $('#empleadoPagoFormLoading').removeClass('d-none');
        $('#empleadoPagoFormContenido').addClass('d-none');
    });

})(jQuery);
