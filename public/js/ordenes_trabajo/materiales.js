/**
 * Consumo de material en un proceso.
 *
 * El precio no se escribe en la pantalla: lo pone el costo promedio del
 * almacen. Aqui solo se previsualiza, leyendo el data-costo que cada
 * opcion del desplegable trae, para que se vea cuanto va a sumar al proceso
 * antes de guardar. Lo que de verdad se cobra lo toma el servidor del
 * saldo, que es el unico que sabe el promedio vigente.
 *
 * Al elegir un material se avisa cuando no hay existencia suficiente, y
 * tambien cuando el material no tiene costo cargado: en ese caso el
 * consumo sumaria cero al costo del proceso sin que nada lo dijera al
 * final, y conviene verlo antes de confirmar.
 */
(function ($) {
    'use strict';

    $(function () {
        const $formulario = $('#formMaterial');

        if (!$formulario.length) {
            return;
        }

        const modalElement = document.getElementById('modalMaterial');
        const modal = modalElement ? new bootstrap.Modal(modalElement) : null;

        const $producto = $('#materialProducto');
        const $cantidad = $('#materialCantidad');
        const $unidad = $('#materialUnidad');
        const $unitario = $('#materialCostoUnitario');
        const $total = $('#materialTotal');
        const $aviso = $('#materialAviso');
        const $error = $('#materialError');

        $('#btnNuevoMaterial').on('click', function () {
            $error.addClass('d-none').empty();
            $aviso.addClass('d-none').removeClass('alert alert-warning alert-danger').empty();

            if (modal) {
                modal.show();
            }

            recalcular();
        });

        /**
         * El material elegido y lo que el almacen sabe de el.
         *
         * Se leen los data-* de la opcion y no se consulta al servidor: son
         * una foto del saldo del momento en que se cargo la pagina, y
         * sirven para avisar. Si entre la carga y el guardado alguien registro
         * otra entrada, el servidor usara el promedio mas reciente, que es lo
         * correcto.
         */
        function materialElegido() {
            const $opcion = $producto.find('option:selected');

            if (!$opcion.length || !$opcion.val()) {
                return null;
            }

            return {
                unidad: $opcion.data('unidad') || '',
                existencia: parseFloat($opcion.attr('data-existencia')) || 0,
                costo: parseFloat($opcion.attr('data-costo')) || 0,
            };
        }

        function recalcular() {
            const material = materialElegido();
            const cantidad = parseFloat($cantidad.val()) || 0;

            $aviso.addClass('d-none').empty();

            if (material === null) {
                $unidad.text('—');
                $unitario.val('0.0000');
                $total.val('0.00');
                return;
            }

            $unidad.text(material.unidad);
            $unitario.val(material.costo.toFixed(4));
            $total.val((material.costo * cantidad).toFixed(2));

            if (material.costo <= 0) {
                $aviso
                    .removeClass('d-none alert-danger')
                    .addClass('alert alert-warning')
                    .html(
                        'Este material no tiene costo cargado en el almacén, así que este ' +
                        'consumo sumará <strong>cero</strong> al costo del proceso. Si no es lo ' +
                        'que quieres, registra antes una entrada con su precio.'
                    );
                return;
            }

            if (cantidad > material.existencia) {
                $aviso
                    .removeClass('d-none alert-warning')
                    .addClass('alert alert-danger')
                    .html(
                        'En el almacén hay <strong>' + material.existencia + '</strong> '
                        + material.unidad + ' y estás consumiendo <strong>' + cantidad + '</strong>. '
                        + 'El servidor no lo va a dejar pasar: o entra antes más material, o baja la cantidad.'
                    );
            }
        }

        $producto.on('change', recalcular);
        $cantidad.on('input', recalcular);

        /*
         * Select2 dibuja su propio combo y esconde el <select> nativo, asi
         * que los eventos hay que escucharlos en el elemento de select2, no
         * en el original.
         */
        if ($producto.data('select2') !== undefined) {
            $producto.on('select2:select', recalcular);
        }

        $formulario.on('submit', function (evento) {
            evento.preventDefault();

            const $enviar = $('#btnGuardarMaterial');
            $enviar.prop('disabled', true);

            $.ajax({
                url: $formulario.data('store-url'),
                method: 'POST',
                data: $formulario.serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                success: function () {
                    window.location.reload();
                },
                error: function (xhr) {
                    $enviar.prop('disabled', false);
                    $error.removeClass('d-none').html(errorDe(xhr));
                },
            });
        });

        /**
* El mensaje del servidor, tal cual.
         *
         * Un rechazo por falta de existencias es lo normal aqui, no una
         * falta: se muestra tal cual para que se entienda que hay que
         * reponer, en vez de un "algo fue mal" que no dice nada.
         */
        function errorDe(xhr) {
            const respuesta = xhr.responseJSON || {};

            if (respuesta.errors) {
                return Object.values(respuesta.errors).join('<br>');
            }

            if (respuesta.message) {
                return respuesta.message;
            }

            return 'No se pudo registrar el consumo. Revise la conexión e intente de nuevo.';
        }
    });
})(jQuery);
