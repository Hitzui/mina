$(document).ready(function () {

    const formulario = $('#formOrdenTrabajo');

    if (!formulario.length) {
        return;
    }

    const modo = formulario.data('modo');
    const tieneOperaciones =
        String(formulario.data('tiene-operaciones')) === '1';


    // ============================================================
    // CLIENTE
    // ============================================================

    $(document).on('cliente:seleccionado', function (event, cliente) {

        $('#cliente_id').val(cliente.id);
        $('#cliente_nombre').val(cliente.nombre);

    });


    // ============================================================
    // FECHA
    // ============================================================

    if (typeof flatpickr !== 'undefined') {

        flatpickr('#fecha', {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            locale: 'es',
            allowInput: true
        });

    }


    // ============================================================
    // INFORMACIÓN DEL FORMULARIO
    // ============================================================

    console.log('Formulario OT inicializado');
    console.log('Modo:', modo);
    console.log('Tiene operaciones:', tieneOperaciones);

});
