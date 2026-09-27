/**
 * Costos de un proceso y de una orden.
 *
 * El total y su equivalente en NIO los calcula el servidor. Aqui solo se
 * previsualiza el total, que es una simple multiplicacion, para que el
 * usuario vea la cifra mientras escribe. El equivalente en NIO no se
 * reimplementa en javascript a proposito: necesita el tipo de cambio de la
 * fecha, y pedirlo al servidor en cada tecla seria una consulta por
 * pulsacion. Se muestra el que devolvio el ultimo guardado.
 */
(function ($) {
    'use strict';

    /**
     * Monta el comportamiento de un modal de costos.
     *
     * Se escribio una fabrica y no dos copias del mismo codigo porque el
     * formulario del proceso y el de la orden son el mismo: si una regla
     * cambiara en uno y no en el otro, los dos mostrarian cifras distintas.
     */
    function montarModal(opciones) {
        const modalElement = document.getElementById(opciones.modalForm);
        const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
        const modalShowElement = document.getElementById(opciones.modalShow);
        const modalShow = modalShowElement ? new bootstrap.Modal(modalShowElement) : null;

        const $formulario = $('#' + opciones.formulario);

        const categoria = $('#' + opciones.prefij + 'Categoria');
        const fecha = $('#' + opciones.prefij + 'Fecha');
        const moneda = $('#' + opciones.prefij + 'Moneda');
        const descripcion = $('#' + opciones.prefij + 'Descripcion');
        const cantidad = $('#' + opciones.prefij + 'Cantidad');
        const unitario = $('#' + opciones.prefij + 'Unitario');
        const total = $('#' + opciones.prefij + 'Total');
        const totalMoneda = $('#' + opciones.prefij + 'TotalMoneda');
        const totalNio = $('#' + opciones.prefij + 'TotalNio');
        const tipoCambio = $('#' + opciones.prefij + 'TipoCambio');
        const aviso = $('#' + opciones.prefij + 'Aviso');
        const error = $('#' + opciones.prefij + 'Error');

        const prefijoShow = opciones.prefijShow;

        /**
         * Select2 dibuja su propio combo y esconde el <select> nativo.
         * Cuando el valor se cambia por codigo con .val(), la libreria no
         * se entera y el usuario sigue viendo el valor anterior: hay que
         * relanzar change para que actualice lo que muestra.
         */
        function sincronizarSelect2($select) {
            if (!$select.length || $select.data('select2') === undefined) {
                return;
            }

            $select.trigger('change');
        }

        function fijar($select, valor) {
            $select.val(valor ?? '');
            sincronizarSelect2($select);
        }

        /**
         * El total es cantidad por costo unitario. Solo se previsualiza: al
         * guardar, el servidor lo vuelve a calcular y es el que queda.
         */
        function calcularTotal() {
            const cantidadValor = parseFloat(cantidad.val()) || 0;
            const unitarioValor = parseFloat(unitario.val()) || 0;

            total.val((cantidadValor * unitarioValor).toFixed(2));

            const opcion = moneda.find('option:selected');
            totalMoneda.text(opcion.length ? (opcion.text().split(' — ')[0] || '—') : '—');
        }

        function limpiarPrevisualizacion() {
            total.val('0.00');
            totalNio.val('—');
            tipoCambio.text('—');
        }

        function limpiarAviso() {
            aviso.addClass('d-none').text('');
        }

        function mostrarAviso(mensaje, tipo) {
            aviso
                .removeClass('d-none alert-danger alert-warning alert-info')
                .addClass('alert-' + tipo)
                .text(mensaje);
        }

        $('#' + opciones.botonNuevo).on('click', function () {
            $formulario[0]?.reset();

            fijar(categoria, '');
            limpiarPrevisualizacion();
            limpiarAviso();
            error.addClass('d-none').text('');

            $('#' + opciones.etiquetaModal).text('Registrar costo');

            $formulario.attr('action', $formulario.data('store-url'));
            $formulario.find('input[name="_method"]').remove();

            modal?.show();
        });

        $(document).on('click', '.btn-show-costo[data-url]', function () {
            const url = $(this).data('url');

            if (!url) {
                return;
            }

            $.ajax({
                url: url,
                type: 'GET',
                success: function (r) {
                    $('#' + prefijoShow + 'Categoria').val(r.categoria ?? '—');
                    $('#' + prefijoShow + 'Fecha').val(r.fecha ?? '—');
                    $('#' + prefijoShow + 'Descripcion').val(r.descripcion ?? '');
                    $('#' + prefijoShow + 'Cantidad').val(numero(r.cantidad, 3));
                    $('#' + prefijoShow + 'Unitario').val(numero(r.costo_unitario, 2));
                    $('#' + prefijoShow + 'Total').val(numero(r.costo_total, 2));
                    $('#' + prefijoShow + 'Moneda').val(r.moneda ?? '—');
                    $('#' + prefijoShow + 'TotalNio').val(
                        r.costo_total_nio === null || r.costo_total_nio === undefined
                            ? 'sin tipo de cambio'
                            : numero(r.costo_total_nio, 2)
                    );
                    $('#' + prefijoShow + 'Observaciones').val(r.observaciones ?? '');

                    modalShow?.show();
                },
                error: function (xhr) {
                    error
                        .removeClass('d-none')
                        .text(xhr.responseJSON?.message ?? 'No se pudo obtener la información del costo.');
                }
            });
        });

        $(document).on('click', '.btn-edit-costo[data-url]', function (e) {
            e.preventDefault();

            const url = $(this).data('url');

            if (!url) {
                return;
            }

            $.ajax({
                url: url,
                type: 'GET',
                success: function (r) {
                    error.addClass('d-none').text('');
                    limpiarAviso();

                    $('#' + opciones.etiquetaModal).text('Editar costo');

                    fijar(categoria, r.categoria_costo_id);
                    fijar(moneda, r.moneda_id);
                    fecha.val(r.fecha ?? '');
                    descripcion.val(r.descripcion ?? '');
                    cantidad.val(r.cantidad ?? 1);
                    unitario.val(r.costo_unitario ?? 0);
                    $('#' + opciones.prefij + 'Observaciones').val(r.observaciones ?? '');

                    // Al editar se muestra lo que quedo guardado, no se
                    // recalcula: el total en NIO es la foto de su fecha
                    total.val(numero(r.costo_total, 2));
                    totalNio.val(
                        r.costo_total_nio === null || r.costo_total_nio === undefined
                            ? '—'
                            : numero(r.costo_total_nio, 2)
                    );
                    tipoCambio.text('guardado');

                    $formulario
                        .attr('action', $formulario.data('update-url').replace('__ID__', r.id));
                    $formulario.find('input[name="_method"]').remove();
                    $formulario.append('<input type="hidden" name="_method" value="PUT">');

                    modal?.show();
                },
                error: function (xhr) {
                    error
                        .removeClass('d-none')
                        .text(xhr.responseJSON?.message ?? 'No se pudo obtener la información del costo.');
                }
            });
        });

        cantidad.on('input', calcularTotal);
        unitario.on('input', calcularTotal);
        moneda.on('change', calcularTotal);

        $formulario.on('submit', function () {
            $(opciones.botonGuardar).prop('disabled', true);
        });

        calcularTotal();
    }

    function numero(valor, decimales) {
        const n = parseFloat(valor);

        if (isNaN(n)) {
            return '—';
        }

        return n.toLocaleString('es-NI', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        });
    }

    $(function () {
        // El modal del proceso, si esta en la pagina
        if (document.getElementById('formCosto')) {
            montarModal({
                formulario: 'formCosto',
                modalForm: 'modalCosto',
                modalShow: 'modalShowCosto',
                botonNuevo: 'btnNuevoCosto',
                botonGuardar: 'btnGuardarCosto',
                etiquetaModal: 'modalCostoLabel',
                prefij: 'costo',
                prefijShow: 'showCosto',
            });
        }

        // El de la orden, en la pantalla de la orden de trabajo
        if (document.getElementById('formCostoOrden')) {
            montarModal({
                formulario: 'formCostoOrden',
                modalForm: 'modalCostoOrden',
                modalShow: 'modalShowCostoOrden',
                botonNuevo: 'btnNuevoCostoOrden',
                botonGuardar: 'btnGuardarCostoOrden',
                etiquetaModal: 'modalCostoOrdenLabel',
                prefij: 'costoOrden',
                prefijShow: 'showCostoOrden',
            });
        }
    });
})(window.jQuery);
