/**
 * Compras: la rejilla de lineas de material.
 *
 * A diferencia de los materiales y los proveedores, aqui no hay modal: una
 * compra lleva las lineas que lleve, y meterlas y quitarlas en una ventana
 * pequena es incomodo. Ademas el total tiene que estar a la vista mientras se
 * rellena, que es justo lo que una ventana tapa.
 *
 * Los totales que se ven aqui son una previsualizacion. Los que se guardan
 * los vuelve a sumar el servidor, porque si el total se aceptara del
 * formulario, el almacen recibiria el material por un importe que no es el de
 * su linea y el costo promedio quedaria con ese error dentro. Por eso aqui no
 * se manda ningun total: solo se mira.
 *
 * Las lineas que ya tiene la compra llegan en un json del servidor, y no se
 * leen del html. Copiar los valores de los inputs a mano es como una tabla y
 * su detalle se desincronizan: se cambia el servidor, la vista sigue
 * enseñando lo viejo, y no hay forma de saber cual de los dos tiene razon.
 */
(function ($) {
    'use strict';

    /*
     * El jQuery se pasa desde fuera, al final del archivo, con
     * (function ($) { ... })(window.jQuery). Si se cierra con los parentesis
     * vacios, aunque el jQuery este cargado, $ vale undefined dentro y el
     * primer $ que se use revienta con un "$ is not a function". Es un fallo
     * facil de volver a introducir, porque escribirlo asi parece correcto.
     *
     * Sin jQuery no se puede hacer nada de lo que hay en este archivo.
     *
     * Antes de que el jQuery se cargara del proyecto venia de una CDN, y sin
     * internet no llegaba. Entonces salia un "$ is not a function" con un
     * numero de linea que no decia nada: no decia que faltaba el jQuery, ni
     * que la pagina no iba a funcionar, ni que habia que recargar. Con este
     * aviso se dice en voz alta, que es la diferencia entre un fallo que se
     * busca solo y uno que se tiene que adivinar.
     */
    if (typeof window.jQuery === 'undefined') {
        console.error(
            'No se encontro jQuery, asi que este script no puede funcionar. '
            + 'Compruebe que el archivo public/plugins/jquery/jquery.min.js '
            + 'este ahi y que la pagina se haya recargado sin la cache.'
        );

        return;
    }

    $(function () {
        const $formulario = $('#formCompra');

        if (!$formulario.length) {
            return;
        }

        const $cuerpo = $('#cuerpoLineas');
        const $plantilla = $('#lineaPlantilla');
        const $error = $('#errorLineas');
        const $avisoEstado = $('#avisoEstado');
        const $estado = $('#estado');
        const $porcentaje = $('#porcentaje_impuesto');
        const $botonGuardar = $('#btnGuardarCompra');

        // ------------------------------------------------------------------
        // El desplegable de material
        //
        // Select2 solo se engancha a los selects que encuentra al abrir la
        // pagina, asi que las filas nuevas hay que activarselas a mano con la
        // funcion que el propio Select2 deja publicada. Sin eso, las lineas
        // que se agreguen despues salen como un select del sistema operativo
        // mientras las de arriba salen con buscador, y el formulario parece
        // de dos tipos.
        // ------------------------------------------------------------------

        function iniciarSelect2En($contenedor) {
            if (typeof window.iniciarSelect2 !== 'function') {
                return;
            }

            window.iniciarSelect2($contenedor);
        }

        function destruirSelect2De($contenedor) {
            $contenedor.find('select.select2').each(function () {
                const $select = $(this);

                // Solo si llego a engancharse. Select2 cuelga el desplegable
                // del body, y si se quita la fila sin soltarlo antes, ese
                // desplegable se queda colgando sin select al que pertenecer.
                if ($select.data('select2') !== undefined) {
                    $select.select2('destroy');
                }
            });
        }

        /**
         * Deja la fila de ejemplo sin desplegable.
         *
         * La fila de ejemplo no es una linea real: solo esta para que haya
         * algo que clonar. Si se le deja el desplegable puesto, Select2 lo
         * engancha al abrir la pagina y dentro de la fila aparece un segundo
         * desplegable fantasma, invisible porque la fila esta oculta pero
         * vivo, y al clonarla cada fila nueva hereda ese trasto.
         *
         * Se quitan la clase y el enganche, en ese orden, porque no se sabe
         * si el Select2 de la pagina ya habra pasado por aqui o pasara
         * despues. Asi el final es el mismo en los dos casos.
         *
         * Lo del name no se toca aqui porque ya no hay nada que tocar: la
         * vista lo escribe en data-nombre y no en name, para que el molde no
         * se mande nunca con el formulario. Se explica entero en la propia
         * vista, que es donde se lee.
         */
        function dejarPlantillaLimpia() {
            $plantilla.find('select').removeClass('select2');

            destruirSelect2De($plantilla);
        }

        // ------------------------------------------------------------------
        // Las lineas que ya tiene la compra, si es que esta editando una
        // ------------------------------------------------------------------

        let lineas = [];

        try {
            const datos = document.getElementById('datosLineas');

            if (datos && datos.textContent.trim() !== '') {
                lineas = JSON.parse(datos.textContent);
            }
        } catch (e) {
            lineas = [];
        }

        // ------------------------------------------------------------------
        // Utilidades
        // ------------------------------------------------------------------

        function dinero(valor) {
            return (Math.round((valor + Number.EPSILON) * 100) / 100).toFixed(2);
        }

        function aNumero(valor) {
            const n = parseFloat(valor);

            return isNaN(n) ? 0 : n;
        }

        function limpiarError() {
            $error.addClass('d-none').empty();
        }

        // ------------------------------------------------------------------
        // La rejilla
        // ------------------------------------------------------------------

        /**
         * Agrega una linea al final de la rejilla.
         *
         * La fila se clona de la plantilla y cada campo toma su nombre de
         * data-nombre, que lleva el indice de ejemplo "__i__". Ese se
         * cambia por el numero de fila, porque el servidor espera
         * productos[0], productos[1], y si el indice se quedara como
         * "__i__" las dos lineas se pisarian entre si.
         */
        function agregarLinea(datos) {
            const indice = $cuerpo.children('.linea-dato').length;

            const $fila = $plantilla.clone()
                .removeClass('d-none linea-plantilla')
                .addClass('linea-dato')
                .attr('data-indice', indice);

            // El id no puede repetirse en el documento
            $fila.find('select').removeAttr('id');
            $fila.find('input').removeAttr('id');

            /*
             * El nombre sale de data-nombre y no de name, porque el molde no
             * lleva name: es un molde, no una linea, y un campo con name se
             * manda aunque la fila este escondida. El "__i__" se cambia por el
             * numero de fila porque el servidor espera productos[0],
             * productos[1], y si el indice se quedara como "__i__" las dos
             * lineas se pisarian entre si.
             */
            $fila.find('select, input').each(function () {
                this.name = (this.getAttribute('data-nombre') || '').replace('__i__', indice);
            });

            const $material = $fila.find('.producto-linea');

            if (datos) {
                $material.val(datos.producto_id);
                $fila.find('.cantidad-linea').val(datos.cantidad);
                $fila.find('.costo-linea').val(datos.costo_unitario);
            }

            $fila.find('.subtotal-linea').text(
                datos
                    ? dinero(aNumero(datos.cantidad) * aNumero(datos.costo_unitario))
                    : dinero(0)
            );

            $cuerpo.append($fila);

            /*
             * El valor se pone antes de enganchar el desplegable: si se
             * enganchara primero, habria que avisarle a mano de que cambio,
             * y basta con repetir la inicializacion para que lo lea.
             */
            $material.addClass('select2');
            iniciarSelect2En($fila);

            recalcular();

            return $fila;
        }

        /**
         * Quita una linea de la rejilla.
         *
         * Se deja al menos una fila viva. Sin ella no habria forma de anadir
         * material sin recargar la pagina, y ademas el formulario se mandaria
         * en vacio y el servidor lo rechazaria con un error de los que cuesta
         * entender.
         */
        function quitarLinea($fila) {
            if ($cuerpo.children('.linea-dato').length <= 1) {
                const $material = $fila.find('.producto-linea');

                $material.val('').trigger('change');

                $fila.find('.cantidad-linea').val('1');
                $fila.find('.costo-linea').val('0.00');
                $fila.find('.subtotal-linea').text(dinero(0));

                recalcular();

                return;
            }

            destruirSelect2De($fila);

            $fila.remove();

            recalcular();
        }

        // ------------------------------------------------------------------
        // Los totales
        // ------------------------------------------------------------------

        /**
         * Vuelve a calcular el subtotal de cada linea y el resumen de abajo.
         *
         * Solo para mirar: lo que se guarda lo suma el servidor. Aqui solo
         * se evita que se pulse guardar sin haber visto la cuenta.
         */
        function recalcular() {
            let subtotal = 0;

            $cuerpo.find('.linea-dato').each(function () {
                const $fila = $(this);

                const cantidad = aNumero($fila.find('.cantidad-linea').val());
                const costo = aNumero($fila.find('.costo-linea').val());

                const parcial = cantidad * costo;

                $fila.find('.subtotal-linea').text(dinero(parcial));

                subtotal += parcial;
            });

            const porcentaje = aNumero($porcentaje.val());
            const impuesto = subtotal * (porcentaje / 100);

            $('#resumenSubtotal').text(dinero(subtotal));
            $('#resumenImpuesto').text(dinero(impuesto));
            $('#resumenTotal').text(dinero(subtotal + impuesto));
        }

        // ------------------------------------------------------------------
        // El estado, que es lo que decide si el material entra
        // ------------------------------------------------------------------

        /**
         * Avisa de que el material entra al almacen solo al finalizar.
         *
         * Se repite al elegir el estado, y no solo en el texto fijo de
         * arriba, porque elegir "Pendiente" y guardar es un camino de sobra
         * para que la compra se quede sin el material sin que nadie lo note
         * hasta que falte en un proceso.
         */
        function avisarDelEstado() {
            const valor = $estado.val();

            $avisoEstado.removeClass('text-muted text-success text-warning');

            if (valor === '3') {
                $avisoEstado
                    .addClass('text-success')
                    .html(
                        '<i class="bi bi-check-circle me-1"></i>' +
                        'Al guardar, el material entra al almacén y el costo promedio se recalcula.'
                    );
            } else if (valor === '0') {
                $avisoEstado
                    .addClass('text-warning')
                    .html(
                        '<i class="bi bi-exclamation-triangle me-1"></i>' +
                        'Al guardar, el material <strong>sale</strong> del almacén, si es que había entrado.'
                    );
            } else {
                $avisoEstado
                    .addClass('text-muted')
                    .text('El material entra al almacén solo al finalizar la compra.');
            }
        }

        // ------------------------------------------------------------------
        // Lo que pasa al pulsar
        // ------------------------------------------------------------------

        /**
         * Revisa que quede alguna linea con material, antes de mandar nada.
         *
         * El servidor lo comprueba tambien y devuelve un error, pero este
         * sale al instante y pegado a la rejilla, que es donde esta el
         * problema. Mandar el formulario entero para que rebote es perder el
         * trabajo de la cabecera.
         */
        function revisarAntesDeGuardar() {
            const completas = $cuerpo.find('.linea-dato').filter(function () {
                return $(this).find('.producto-linea').val() !== '';
            });

            if (completas.length === 0) {
                mostrarError(
                    'La compra tiene que llevar al menos una línea de material. Elija un material en alguna línea.'
                );

                return false;
            }

            for (let i = 0; i < completas.length; i++) {
                if (aNumero(completas.eq(i).find('.cantidad-linea').val()) <= 0) {
                    mostrarError(
                        'La cantidad tiene que ser mayor que cero. Revise la línea ' + (i + 1) + '.'
                    );

                    return false;
                }
            }

            limpiarError();

            return true;
        }

        function mostrarError(mensaje) {
            $error
                .removeClass('d-none')
                .html('<i class="bi bi-exclamation-triangle me-1"></i>' + mensaje);
        }

        // ------------------------------------------------------------------
        // Los eventos
        // ------------------------------------------------------------------

        $('#btnAgregarLinea').on('click', function () {
            const $fila = agregarLinea();

            limpiarError();

            $fila.find('.producto-linea').trigger('focus');
        });

        $cuerpo.on('click', '.quitar-linea', function () {
            quitarLinea($(this).closest('tr'));
        });

        /*
         * Con delegacion, para que tambien funcione en las filas que se
         * agreguen despues de que se registrara el evento.
         */
        $cuerpo.on('input change', '.cantidad-linea, .costo-linea', function () {
            recalcular();
        });

        $porcentaje.on('input change', function () {
            recalcular();
        });

        $estado.on('change', function () {
            avisarDelEstado();
        });

        $formulario.on('submit', function (evento) {
            if (!revisarAntesDeGuardar()) {
                evento.preventDefault();

                return false;
            }

            $botonGuardar.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...'
            );

            return true;
        });

        // ------------------------------------------------------------------
        // La rejilla al abrir la pantalla
        // ------------------------------------------------------------------

        dejarPlantillaLimpia();

        if (lineas.length > 0) {
            lineas.forEach(function (linea) {
                agregarLinea(linea);
            });
        } else {
            agregarLinea();
        }

        recalcular();
        avisarDelEstado();
    });
})(window.jQuery);
