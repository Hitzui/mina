$(document).ready(function () {

    const formulario = $('#formProcesoOrden');

    if (!formulario.length) {
        return;
    }


    // ============================================================
    // FECHA DE INICIO
    // ============================================================

    if (typeof flatpickr !== 'undefined') {

        flatpickr('#fecha_inicio', {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            altInput: true,
            altFormat: 'd/m/Y H:i',
            locale: 'es',
            time_24hr: true,
            allowInput: true
        });


        // ========================================================
        // FECHA DE FINALIZACIÓN
        // ========================================================

        flatpickr('#fecha_fin', {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            altInput: true,
            altFormat: 'd/m/Y H:i',
            locale: 'es',
            time_24hr: true,
            allowInput: true
        });

    }

});
