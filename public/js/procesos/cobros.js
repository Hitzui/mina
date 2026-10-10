/**
 * Cobros: el formulario de un cobro y su rejilla de ingresos.
 *
 * ===========================================================================
 * LO QUE HACE ESTE ARCHIVO Y LO QUE NO
 * ===========================================================================
 *
 * Aqui no se guarda nada. El total que se ve arriba es una previsualizacion: lo
 * vuelve a sumar el servidor desde las lineas, porque si el total se aceptara del
 * formulario, un total equivocado entraria en la base y la suma del detalle dejaria
 * de coincidir sin que nada lo dijera. Por eso el campo del total no tiene name y
 * no se manda.
 *
 * Las lineas que ya tiene el cobro llegan en un json del servidor y no se leen del
 * html. Copiar los valores de los inputs a mano es como una tabla y su detalle se
 * desincronizan: se cambia el servidor, la vista sigue enseñando lo viejo, y no hay
 * forma de saber cual de los dos tiene razon.
 *
 * ===========================================================================
 * Y EL AJAX DE LOS INGRESOS DEL CLIENTE
 * ===========================================================================
 *
 * Las lineas de un cobro son los ingresos de SU cliente, de modo que la lista entera
 * depende de una eleccion que esta arriba. Van por ajax y no en la pagina por dos
 * razones, y las dos son reales:
 *
 *   - Mandar en la pagina los ingresos de todos los clientes haria que cada ficha
 *     de cobro cargara el catalogo entero. El taller tiene 7 clientes hoy; con
 *     cuatrocientos, cada edicion de cobro arrastraria cuatrocientas filas que no
 *     son suyas.
 *
 *   - Y la lista cambia sin que se recargue nada: entre que se abre el formulario y
 *     se elige el cliente, alguien puede haber facturado algo. Con los datos en la
 *     pagina, el desplegable ofrecería una lista vieja.
 *
 * Y el fallo NO SE CALLA: si la peticion falla, la rejilla se queda vacia y sale un
 * aviso que dice que no es que el cliente no tenga ingresos. Un desplegable vacio sin
 * explicacion es exactamente el sintoma que hace que el usuario busque el error en el
 * sitio equivocado.
 */
(function ($) {
    'use strict';

    /*
     * El jQuery se pasa desde fuera, al final del archivo, con
     * (function ($) { ... })(window.jQuery). Si se cierra con los parentesis
     * vacios, aunque el jQuery este cargado, $ vale undefined dentro y el primer $
     * que se use revienta con un "$ is not a function". Es un fallo facil de volver
     * a introducir, porque escribirlo asi parece correcto.
     *
     * Sin jQuery no se puede hacer nada de lo que hay en este archivo.
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
        const $formulario = $('#formCobro');

        if (!$formulario.length) {
            return;
        }

        const $cuerpo = $('#cuerpoLineas');
        const $plantilla = $('#lineaPlantilla');
        const $error = $('#errorLineas');
        const $total = $('#totalCobro');
        const $avisoSinCliente = $('#avisoSinCliente');
        const $botonAgregar = $('#btnAgregarLinea');
        const $botonGuardar = $('#btnGuardarCobro');
        const $cliente = $('#cliente_id');
        const $clienteNombre = $('#clienteNombre');
        const $modalCliente = $('#modalSeleccionCliente');
        const $buscarCliente = $('#buscarCliente');
        const $estado = $('#estado');
        const $ayudaEstado = $('#ayudaEstado');
        const $moneda = $('#moneda_id');
        const $textoMoneda = $('#textoMonedaTotal');

        let datos = leerDatos();

        /**
         * Lo que el servidor deja puesto en el formulario.
         *
         * Y todo con valor por defecto: si el script se ejecuta en una pagina donde
         * el bloque no esta —o se quedo a medias si el navegador corto la carga—,
         * esto no revienta. Un fallo de red que tumba la pantalla entera del alta
         * por un json que no llego es peor que un formulario que deja hacer las
         * cosas sin el refuerzo de la vista.
         */
        function leerDatos() {
            const vacio = {
                cobro_id: null,
                moneda_id: null,
                ingresos: [],
                lineas: [],
                urlIngresos: ''
            };

            try {
                const nodo = document.getElementById('datosCobro');

                if (!nodo || nodo.textContent.trim() === '') {
                    return vacio;
                }

                const leido = JSON.parse(nodo.textContent);

                return {
                    cobro_id: leido.cobro_id ?? null,
                    moneda_id: leido.moneda_id ?? null,
                    ingresos: Array.isArray(leido.ingresos) ? leido.ingresos : [],
                    lineas: Array.isArray(leido.lineas) ? leido.lineas : [],
                    urlIngresos: leido.urlIngresos || ''
                };
            } catch (e) {
                console.error('No se pudieron leer los datos del cobro.', e);

                return vacio;
            }
        }

        // ------------------------------------------------------------------
        // El desplegable de ingreso
        //
        // Select2 solo se engancha a los selects que encuentra al abrir la
        // pagina, asi que las filas nuevas hay que activarselas a mano con la
        // funcion que el propio Select2 deja publicada. Sin eso, las lineas que
        // se agreguen despues salen como un select del sistema operativo
        // mientras las de arriba salen con buscador, y el formulario parece de
        // dos tipos.
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

                /*
                 * Solo si llego a engancharse. Select2 cuelga el desplegable del
                 * body, y si se quita la fila sin soltarlo antes, ese desplegable se
                 * queda colgando sin select al que pertenecer.
                 */
                if ($select.data('select2') !== undefined) {
                    $select.select2('destroy');
                }
            });
        }

        /**
         * Deja la fila de ejemplo sin desplegable.
         *
         * La fila de ejemplo no es una linea real: solo esta para que haya algo que
         * clonar. Si se le deja el desplegable puesto, Select2 lo engancha al abrir
         * la pagina y dentro de la fila aparece un segundo desplegable fantasma,
         * invisible porque la fila esta oculta pero vivo, y al clonarla cada fila
         * nueva hereda ese trasto.
         *
         * Se quitan la clase y el enganche, en ese orden, porque no se sabe si el
         * Select2 de la pagina ya habra pasado por aqui o pasara despues. Asi el
         * final es el mismo en los dos casos.
         *
         * Lo del name no se toca aqui porque ya no hay nada que tocar: la vista lo
         * escribe en data-nombre y no en name, para que el molde no se mande nunca
         * con el formulario. Se explica entero en la propia vista, que es donde se
         * lee.
         */
        function dejarPlantillaLimpia() {
            $plantilla.find('select').removeClass('select2');

            destruirSelect2De($plantilla);
        }

        // ------------------------------------------------------------------
        // Utilidades
        // ------------------------------------------------------------------

        /**
         * Los numeros, en el formato de Nicaragua.
         *
         * Coma en los miles y punto en los decimales, que es como los ve el
         * usuario en la calle y como los pide. Es el mismo Intl.NumberFormat que
         * usan las demas pantallas; se pasa es-NI explicito porque el constructor
         * sin argumentos toma la del navegador, y el taller mira la pantalla en
         * equipos distintos: el formato por defecto sale diferente en cada uno.
         */
        const formateador = new Intl.NumberFormat('es-NI', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        function dinero(valor) {
            return formateador.format(Math.round((valor + Number.EPSILON) * 100) / 100);
        }

        function aNumero(valor) {
            const n = parseFloat(valor);

            return isNaN(n) ? 0 : n;
        }

        function limpiarError() {
            $error.addClass('d-none').empty();
        }

        /** Filtra los clientes del modal por nombre o teléfono. */
        function filtrarClientes() {
            const busqueda = String($buscarCliente.val() || '').trim().toLocaleLowerCase();
            const $opciones = $modalCliente.find('.cliente-opcion');
            let visibles = 0;

            $opciones.each(function () {
                const texto = String($(this).attr('data-cliente-busqueda') || '').toLocaleLowerCase();
                const coincide = texto.includes(busqueda);

                $(this).toggleClass('d-none', !coincide);

                if (coincide) {
                    visibles++;
                }
            });

            $('#sinClientesEncontrados').toggleClass(
                'd-none',
                busqueda === '' || visibles > 0 || $opciones.length === 0
            );
        }

        function mostrarError(mensaje) {
            $error
                .removeClass('d-none')
                .html('<i class="bi bi-exclamation-triangle me-1"></i>' + mensaje);
        }

        // ------------------------------------------------------------------
        // Los ingresos del cliente
        // ------------------------------------------------------------------

        /**
         * Cambia las opciones del desplegable de ingreso de TODAS las filas.
         *
         * Se cambian todas y no solo la plantilla, porque cuando llega el cliente
         * ya puede haber lineas en la rejilla —se conservan al cambiar de cliente
         * para que no se pierda lo escrito, y se avisa de si eso deja algo invalido
         * mas abajo— y una fila con opciones viejas dejaria elegir un ingreso del
         * cliente anterior, que es justo el error que la regla del servidor
         * rechaza con el mensaje mas incomodo de toda la pantalla.
         */
        function ponerIngresosEnLasFilas(ingresos) {
            const opciones = construirOpciones(ingresos);

            $cuerpo.find('.ingreso-linea').each(function () {
                const $select = $(this);
                const elegido = $select.val();

                if ($select.data('select2') !== undefined) {
                    $select.select2('destroy');
                }

                $select.empty().append(opciones.clone());
                $select.val(elegido === null ? '' : elegido);
            });

            refrescarResumenDeCadaFila();
        }

        /**
         * Las <option> del desplegable.
         *
         * El texto lleva el total del ingreso y cuanto lleva cobrado, porque esa es
         * la informacion que evita tener que abrir cada ingreso para saber si
         * conviene usarlo: un ingreso ya pagado del todo se reconoce ahi y no se
         * elige.
         *
         * Y los atributos data-* llevan los numeros, porque el texto puede llevar
         * separadores de miles y "3.000,00" no se puede leer con parseFloat: el
         * separador de miles en Nicaragua es la coma y el decimal el punto, al reves
         * que en casi todas partes, y parseFloat("3.000,00") devuelve 3.
         */
        function construirOpciones(ingresos) {
            /*
             * Las opciones se construyen dentro de un select temporal para que
             * queden como hermanos. Anidar <option> dentro de otra <option>
             * convierte el marcador y el primer ingreso en una sola etiqueta:
             * se ve el texto pegado, pero el select sigue sin un ingreso elegido.
             */
            const $contenedor = $('<select></select>');

            $('<option value=""></option>')
                .text('Seleccione el ingreso')
                .appendTo($contenedor);

            ingresos.forEach(function (ingreso) {
                const texto = ingreso.tipo + ' — ' + ingreso.codigo_orden + ' — ' + ingreso.fecha
                    + ' (' + dinero(ingreso.total) + ')'
                    + (ingreso.cobrado > 0 ? ' — cobrado ' + dinero(ingreso.cobrado) : '');

                $('<option></option>')
                    .attr('value', ingreso.id)
                    .attr('data-total', ingreso.total)
                    .attr('data-moneda', ingreso.moneda_id)
                    .attr('data-cobrado', ingreso.cobrado)
                    .attr('data-texto', texto)
                    .text(texto)
                    .appendTo($contenedor);
            });

            return $contenedor.children('option');
        }

        /**
         * El token de la peticion.
         *
         * Se lee del propio formulario y no de <meta name="csrf-token">, y no por
         * gusto: el layout de este proyecto NO trae esa meta —se comprobo— y los
         * scripts que la leen estan mandando la cadena "undefined" como token. El
         * formulario, en cambio, lleva @csrf, o sea que su input oculto _token esta
         * ahi siempre, en la misma pagina y aunque el resto falle.
         *
         * La meta se mira despues, no antes: si alguien la añade al layout mas
         * adelante, esto lo usa sin tocar nada, y si no, no pasa nada.
         */
        function token() {
            const delFormulario = $formulario.find('input[name="_token"]').val();
            const deLaMeta = $('meta[name="csrf-token"]').attr('content');

            return delFormulario || deLaMeta || '';
        }

        /**
         * Pide los ingresos del cliente y los pone en las filas.
         *
         * Y se avisa de cada fallo por su nombre, porque hay tres cosas distintas
         * que pueden salir mal y las tres se ven igual si no se dice cual:
         *
         *   - Que no haya ingresos: eso NO es un fallo, es un cliente que no debe
         *     nada. Y aun asi hay que decirlo, porque un desplegable vacio sin texto
         *     parece un fallo y el usuario va a buscar el error.
         *
         *   - Que la peticion falle: eso si es un fallo, y hay que decirlo para que
         *     recargue en vez de pensar que ese cliente no tiene nada.
         *
         *   - Y que llegue una respuesta que no es la que se esperaba: se avisa por
         *     consola y se trata como fallo, no como "este cliente no tiene
         *     ingresos". Confundir un error con un dato vacio es como se borra el
         *     sintoma sin borrar la causa.
         */
        function cargarIngresos(clienteId) {
            if (!clienteId) {
                datos.ingresos = [];

                ponerIngresosEnLasFilas([]);

                actualizarEstadoDeLaRejilla();

                return;
            }

            if (!datos.urlIngresos) {
                console.error('No se paso la direccion de los ingresos del cliente.');

                actualizarEstadoDeLaRejilla();
                mostrarError(
                    'No se pudieron cargar los ingresos del cliente. Recargue la pantalla.'
                );

                return;
            }

            $.ajax({
                url: datos.urlIngresos,
                method: 'POST',
                dataType: 'json',
                data: {
                    cliente_id: clienteId,
                    cobro_id: datos.cobro_id
                },
                headers: {
                    'X-CSRF-TOKEN': token()
                },
                success: function (respuesta) {
                    if (!respuesta || !Array.isArray(respuesta.ingresos)) {
                        console.error('La respuesta de ingresos no tiene la forma esperada.', respuesta);

                        actualizarEstadoDeLaRejilla();
                        mostrarError(
                            'Los ingresos del cliente llegaron en un formato inesperado. Recargue la pantalla.'
                        );

                        return;
                    }

                    datos.ingresos = respuesta.ingresos;

                    ponerIngresosEnLasFilas(respuesta.ingresos);

                    actualizarEstadoDeLaRejilla();

                    if (respuesta.ingresos.length === 0) {
                        mostrarError(
                            'Este cliente no tiene ningún ingreso. Un cobro reparte dinero entre ingresos, '
                            + 'así que primero hay que registrarle alguno en su orden de trabajo.'
                        );
                    } else {
                        limpiarError();
                    }
                },
                error: function () {
                    datos.ingresos = [];

                    actualizarEstadoDeLaRejilla();

                    mostrarError(
                        'No se pudieron cargar los ingresos del cliente. Puede que se haya perdido la '
                        + 'conexión: recargue la pantalla antes de seguir.'
                    );
                }
            });
        }

        // ------------------------------------------------------------------
        // La rejilla
        // ------------------------------------------------------------------

        /**
         * Agrega una linea al final de la rejilla.
         *
         * La fila se clona de la plantilla y cada campo toma su nombre de
         * data-nombre, que lleva el indice de ejemplo "__i__". Ese se cambia por el
         * numero de fila, porque el servidor espera ingresos[0], ingresos[1], y si el
         * indice se quedara como "__i__" las dos lineas se pisarian entre si.
         */
        function agregarLinea(linea) {
            const indice = $cuerpo.children('.linea-dato').length;

            const $fila = $plantilla.clone()
                .removeClass('d-none linea-plantilla')
                .addClass('linea-dato')
                .attr('data-indice', indice);

            // La plantilla está deshabilitada para no bloquear la validación nativa.
            // Al clonar una fila real, sus controles vuelven a participar.
            $fila.find('select, input').prop('disabled', false);

            // El id no puede repetirse en el documento
            $fila.find('select').removeAttr('id');
            $fila.find('input').removeAttr('id');

            /*
             * El nombre sale de data-nombre y no de name, porque el molde no lleva
             * name: es un molde, no una linea, y un campo con name se manda aunque la
             * fila este escondida.
             */
            $fila.find('select, input').each(function () {
                this.name = (this.getAttribute('data-nombre') || '').replace('__i__', indice);
            });

            const $ingreso = $fila.find('.ingreso-linea');

            if (linea) {
                $ingreso.val(String(linea.ingreso_id));
                $fila.find('.monto-linea').val(dinero(linea.monto));
                $fila.find('.nota-linea').val(linea.observaciones || '');
            }

            $cuerpo.append($fila);

            /*
             * El valor se pone antes de enganchar el desplegable: si se enganchara
             * primero, habria que avisarle a mano de que cambio, y basta con repetir
             * la inicializacion para que lo lea.
             */
            $ingreso.addClass('select2');
            iniciarSelect2En($fila);

            refrescarResumenDeLaFila($fila);
            recalcular();

            return $fila;
        }

        /**
         * Quita una linea de la rejilla.
         *
         * Y no se deja el "como en las compras": alli la fila vacia es una plantilla
         * comoda. Aqui no, porque en cuanto se elige un cliente se pinta una fila
         * con sus ingresos para que haya algo donde escribir, y si esa fila se
         * quedara puesta al quitar la ultima se veria un desplegable con las
         * opciones del cliente en una rejilla que el usuario acaba de vaciar.
         *
         * Con cero lineas, el boton de agregar es el que las trae de vuelta, y sale
         * activado.
         */
        function quitarLinea($fila) {
            destruirSelect2De($fila);

            $fila.remove();

            renumerar();

            recalcular();
        }

        /**
         * Vuelve a numerar las filas despues de quitar una.
         *
         * Sin esto los indices quedan con huecos: se quitaria la fila 1 de 3, quedarian
         * la 0 y la 2, y al anadir la siguiente el nombre seria ingresos[3] con dos
         * filas en medio. No romperia nada —el servidor acepta indices sueltos— pero
         * el mensaje de error "revise la linea 3" hablaria de una fila que ya no
         * existe, que es el fallo mas caro de acertar en un formulario con varias.
         */
        function renumerar() {
            $cuerpo.children('.linea-dato').each(function (indice) {
                const $fila = $(this);

                $fila.attr('data-indice', indice);

                $fila.find('select, input').each(function () {
                    if (!this.name) {
                        return;
                    }

                    this.name = this.name.replace(/\[\d+\]/, '[' + indice + ']');
                });
            });
        }

        // ------------------------------------------------------------------
        // El resumen de cada linea
        // ------------------------------------------------------------------

        /**
         * El texto de al lado del ingreso: de cuanto es, cuanto se le ha cobrado ya.
         *
         * Es el mismo dato que sale en la ficha, aqui para poder elegir sin abrir
         * nada. Y avisa de los dos casos en los que elegir la linea es una mala idea:
         * un ingreso ya pagado del todo, y uno al que se le pasa.
         *
         * Con monedas distintas NO se dice que este bien ni que este mal: el importe
         * de la linea va en la moneda del cobro y el total del ingreso en la suya, y
         * sin una conversion guardada no hay forma de compararlos. Se dice
         * explicitamente que no se puede comprobar, porque un texto en blanco ahi
         * parece un fallo del script y uno que dice "correcto" seria mentira.
         */
        function refrescarResumenDeLaFila($fila) {
            const $ingreso = $fila.find('.ingreso-linea');
            const $resumen = $fila.find('.resumen-ingreso');

            const opcion = $ingreso.find('option:selected');

            if (!opcion.length || !opcion.val()) {
                $resumen
                    .removeClass('text-secondary text-success text-danger text-warning')
                    .addClass('text-secondary')
                    .text('—');

                return;
            }

            const total = aNumero(opcion.attr('data-total'));
            const cobrado = aNumero(opcion.attr('data-cobrado'));
            const monedaIngreso = opcion.attr('data-moneda');

            if (String(monedaIngreso) !== String(datos.moneda_id)) {
                $resumen
                    .removeClass('text-secondary text-success text-danger text-warning')
                    .addClass('text-secondary')
                    .html(
                        '<span class="d-block">de ' + dinero(total) + '</span>'
                        + '<span class="d-block">en otra moneda: no se puede comprobar</span>'
                        + (cobrado > 0
                            ? '<span class="d-block">cobrados ' + dinero(cobrado) + ' en la del cobro</span>'
                            : '')
                    );

                return;
            }

            $resumen.removeClass('text-success text-danger text-warning').addClass('text-secondary');

            let texto = '<span class="d-block">de ' + dinero(total) + '</span>';

            if (cobrado <= 0) {
                $resumen.addClass('text-success');
                texto += '<span class="d-block">sin nada cobrado</span>';
            } else if (cobrado >= total - 0.005) {
                $resumen.removeClass('text-secondary').addClass('text-warning');
                texto += '<span class="d-block">ya cobrado todo (' + dinero(cobrado) + ')</span>';
            } else {
                texto += '<span class="d-block">cobrados ' + dinero(cobrado)
                    + ' — quedan ' + dinero(total - cobrado) + '</span>';
            }

            $resumen.html(texto);
        }

        function refrescarResumenDeCadaFila() {
            $cuerpo.find('.linea-dato').each(function () {
                refrescarResumenDeLaFila($(this));
            });
        }

        // ------------------------------------------------------------------
        // El total
        // ------------------------------------------------------------------

        /**
         * Vuelve a calcular el total que se ve arriba.
         *
         * Solo para mirar: lo que se guarda lo suma el servidor desde las lineas. Aqui
         * solo se evita que se pulse guardar sin haber visto la cuenta.
         *
         * Y va arriba del todo, y no debajo de la rejilla como en las compras, porque
         * aqui la rejilla crece hacia abajo: abajo el total se sale de la vista en
         * cuanto hay cuatro lineas, y quien esta repartiendo el dinero necesita ver si
         * lleva la cuenta bien mientras lo hace.
         */
        function recalcular() {
            let suma = 0;

            $cuerpo.find('.linea-dato').each(function () {
                suma += aNumero($(this).find('.monto-linea').val());
            });

            $total.text(dinero(suma));

            revisarRepetidos();
        }

        /**
         * Pone el nombre de la moneda debajo del total.
         *
         * Y el total se cambia con ella, porque cambiar la moneda cambia lo que
         * significan las cifras de al lado de cada linea: si se pasa de cordoba a
         * dolares sin tocar las lineas, los importes ya no son los que se recibieron
         * y el total mostrado seria una suma de dolares hecha con cifras en
         * cordoba. Se avisa de eso en vez de recalcular nada: convertir un importe no
         * lo sabe hacer nadie aqui, y el que lo sabe es el usuario, que es quien
         * sabe a cuanto cambio hoy.
         *
         * Y no se avisa solo al cambiar la moneda sino tambien al abrir: el total
         * aparece con la moneda ya elegida y sin decir cual es, y eso solo se
         * resuelve con que el nombre este ahi desde el primer momento.
         */
        function ponerTextoDeMoneda() {
            const codigo = $moneda.find('option:selected').attr('data-codigo');

            if (!codigo) {
                $textoMoneda.text('en la moneda elegida');

                return;
            }

            $textoMoneda.text('en ' + codigo);
        }

        /**
         * Avisa de que cambiar la moneda no convierte las líneas.
         *
         * Y solo cuando ya hay importes escritos, porque antes de eso no hay nada
         * que convertir y el aviso sería ruido.
         */
        function avisarDelCambioDeMoneda() {
            const conImportes = $cuerpo.find('.linea-dato').filter(function () {
                return aNumero($(this).find('.monto-linea').val()) > 0;
            }).length > 0;

            if (conImportes) {
                mostrarError(
                    'Ha cambiado la moneda del cobro y los importes de las líneas no se han convertido. '
                    + 'Revise las líneas: sus importes siguen siendo los que escribió, y ahora el total '
                    + 'está en la moneda nueva.'
                );

                return;
            }

            if (datos.ingresos.length > 0) {
                limpiarError();
            }
        }

        // ------------------------------------------------------------------
        // Los avisos mientras se escribe
        // ------------------------------------------------------------------

        /**
         * Avisa de que un ingreso esta en dos lineas del mismo cobro.
         *
         * El servidor lo rechaza, pero el mensaje del servidor llega con la pagina
         * cargada y el trabajo de la cabecera se ha perdido. Aqui se avisa mientras se
         * escribe, que es cuando se puede arreglar.
         *
         * Y el motivo de que sea un error —y no se permita de cualquier manera— esta
         * en el propio texto del aviso: repartir el mismo dinero entre dos lineas del
         * MISMO cobro no dice nada, porque es una fila partida en dos. Si lo que se
         * quiere es cobrar en dos veces, son dos cobros.
         */
        function revisarRepetidos() {
            const vistos = {};

            let repetido = '';

            $cuerpo.find('.linea-dato').each(function () {
                const valor = $(this).find('.ingreso-linea').val();

                if (!valor) {
                    return;
                }

                if (vistos[valor]) {
                    repetido = valor;

                    return false;
                }

                vistos[valor] = true;
            });

            if (repetido) {
                mostrarError(
                    'El ingreso #' + repetido + ' está en dos líneas de este cobro. '
                    + 'Repartir el mismo dinero entre dos líneas no dice nada: si quiere cobrarlo '
                    + 'en dos veces, hágalo en dos cobros.'
                );
            }

            return repetido;
        }

        /**
         * Avisa de que el estado elegido cambia lo que cuenta.
         *
         * Se repite al elegir el estado, y no solo en el texto fijo de la vista,
         * porque poner un cobro en Finalizada es decir que ese dinero esta confirmado
         * en la caja. Elegirlo sin querer y guardar es un camino de sobra para que
         * un cobro que nadie ha recibido cuente como recibido, y eso no se ve en la
         * tabla de cobros mas adelante.
         */
        function avisarDelEstado() {
            const valor = String($estado.val());

            $ayudaEstado.removeClass('text-muted text-success text-warning text-danger');

            if (valor === '3') {
                $ayudaEstado
                    .addClass('text-success')
                    .html(
                        '<i class="bi bi-check-circle me-1"></i>'
                        + 'Al guardar, este dinero cuenta como cobrado en los ingresos.'
                    );
            } else if (valor === '0') {
                $ayudaEstado
                    .addClass('text-danger')
                    .html(
                        '<i class="bi bi-x-octagon me-1"></i>'
                        + 'Al guardar, estas líneas <strong>no cuentan</strong> como cobrado.'
                    );
            } else if (valor === '2') {
                $ayudaEstado
                    .addClass('text-warning')
                    .html(
                        '<i class="bi bi-hourglass-split me-1"></i>'
                        + 'Al guardar, sigue sin contar como cobrado: el dinero todavía no está confirmado.'
                    );
            } else {
                $ayudaEstado
                    .addClass('text-muted')
                    .text('Pendiente: se anotó el cobro, pero el dinero todavía no llegó.');
            }
        }

        /**
         * Habilita o deshabilita lo que depende de que haya ingresos.
         *
         * El boton de agregar linea se habilita solo cuando hay ingresos que repartir.
         * Con el boton apagado no hay un pulsón perdido: el boton apagado, con su
         * title, es la respuesta.
         *
         * Y el aviso de "elija un cliente" solo se ve cuando no hay cliente, y el de
         * "no hay ingresos" cuando hay cliente y no tiene ninguno. Son dos estados
         * distintos con dos arreglos distintos, y mostrar siempre el primero dejaria
         * al que ya eligio cliente buscando un cliente que ya eligio.
         */
        function actualizarEstadoDeLaRejilla() {
            const hayCliente = Boolean($cliente.val());
            const hayIngresos = datos.ingresos.length > 0;

            $botonAgregar
                .prop('disabled', !hayIngresos)
                .attr(
                    'title',
                    hayIngresos
                        ? 'Agregar otra línea'
                        : (hayCliente
                            ? 'Este cliente no tiene ingresos que repartir'
                            : 'Elija primero el cliente')
                );

            $avisoSinCliente.toggleClass('d-none', hayCliente);
        }

        // ------------------------------------------------------------------
        // Lo que pasa al pulsar
        // ------------------------------------------------------------------

        /**
         * Revisa que quede alguna linea con ingreso, antes de mandar nada.
         *
         * El servidor lo comprueba tambien y devuelve un error, pero este sale al
         * instante y pegado a la rejilla, que es donde esta el problema. Mandar el
         * formulario entero para que rebote es perder el trabajo de la cabecera.
         */
        function revisarAntesDeGuardar() {
            if (!$cliente.val()) {
                mostrarError('Elija a quién se le cobra.');

                return false;
            }

            const completas = $cuerpo.find('.linea-dato').filter(function () {
                return $(this).find('.ingreso-linea').val() !== '';
            });

            if (completas.length === 0) {
                mostrarError(
                    'El cobro tiene que llevar al menos una línea de ingreso: un cobro sin ingresos '
                    + 'no dice a qué se ha cobrado.'
                );

                return false;
            }

            for (let i = 0; i < completas.length; i++) {
                if (aNumero(completas.eq(i).find('.monto-linea').val()) <= 0) {
                    mostrarError(
                        'El importe tiene que ser mayor que cero. Revise la línea ' + (i + 1) + '.'
                    );

                    return false;
                }
            }

            if (revisarRepetidos()) {
                return false;
            }

            limpiarError();

            return true;
        }

        // ------------------------------------------------------------------
        // Los eventos
        // ------------------------------------------------------------------

        /*
         * Cambiar de cliente sustituye la lista de ingresos entera.
         *
         * Y se avisa antes de tirar lo escrito si hay lineas con algo puesto, porque
         * cambiar de cliente sin querer se lleva por delante el reparto que llevaba
         * medio hecho, y en un formulario largo eso es trabajo repetido.
         */
        $buscarCliente.on('input', filtrarClientes);

        $modalCliente.on('shown.bs.modal', function () {
            $buscarCliente.val('');
            filtrarClientes();
            $buscarCliente.trigger('focus');
        });

        $modalCliente[0].addEventListener('hide.bs.modal', function () {
            const elementoEnfocado = document.activeElement;

            if (elementoEnfocado && this.contains(elementoEnfocado)) {
                elementoEnfocado.blur();
            }
        });

        $modalCliente[0].addEventListener('hidden.bs.modal', function () {
            $('#btnSeleccionarCliente').trigger('focus');
        });

        $modalCliente.on('click', '.cliente-opcion', function () {
            const nuevoId = String($(this).attr('data-cliente-id') || '');
            const nuevoNombre = String($(this).attr('data-cliente-nombre') || '');
            const clienteActual = String($cliente.val() || '');

            if (nuevoId !== clienteActual) {
                const conLineas = $cuerpo.find('.linea-dato').filter(function () {
                    return $(this).find('.ingreso-linea').val() !== '';
                }).length > 0;

                if (conLineas && !window.confirm(
                    'Ha cambiado el cliente y las líneas se van a quedar sin ingresos.\n\n'
                    + '¿Quiere continuar? Lo que haya escrito en los importes se pierde.'
                )) {
                    return;
                }

                $cliente.val(nuevoId);
                $clienteNombre.val(nuevoNombre);
                $cliente.trigger('change');
            }

            if (window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance($modalCliente[0]).hide();
            }
        });

        $cliente.on('change', function () {
            cargarIngresos($cliente.val());
        });

        $botonAgregar.on('click', function () {
            const $fila = agregarLinea();

            limpiarError();

            $fila.find('.ingreso-linea').trigger('focus');
        });

        $cuerpo.on('click', '.quitar-linea', function () {
            quitarLinea($(this).closest('tr'));
        });

        /*
         * Con delegacion, para que tambien funcione en las filas que se agreguen
         * despues de que se registrara el evento.
         */
        $cuerpo.on('input change', '.monto-linea', function () {
            recalcular();
        });

        $cuerpo.on('change', '.ingreso-linea', function () {
            refrescarResumenDeLaFila($(this).closest('tr'));
            revisarRepetidos();
        });

        $estado.on('change', function () {
            avisarDelEstado();
        });

        $moneda.on('change', function () {
            ponerTextoDeMoneda();
            refrescarResumenDeCadaFila();
            avisarDelCambioDeMoneda();
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

        if (datos.lineas.length > 0) {
            datos.lineas.forEach(function (linea) {
                agregarLinea(linea);
            });
        } else if (datos.ingresos.length > 0) {
            /*
             * Alta con cliente ya elegido: se pone una linea para que haya donde
             * escribir, en vez de una rejilla vacia con un boton. Una rejilla vacia
             * con un boton hay que pulsarlo para saber si funciona; una linea puesta
             * se ve que funciona.
             *
             * En edicion no se hace: si el cobro se guardo sin lineas —que no puede
             * pasar, pero si un cobro viejo de otra herramienta— no se inventa una
             * linea, porque apareceria una linea a medio rellenar que parece del
             * cobro.
             */
            agregarLinea();
        }

        // Si el alta volvió con un cliente elegido y un error de servidor, recupera
        // por Ajax sus ingresos igual que si se acabara de seleccionar en el modal.
        if ($cliente.val() && datos.ingresos.length === 0) {
            cargarIngresos($cliente.val());
        }

        recalcular();
        avisarDelEstado();
        ponerTextoDeMoneda();
        actualizarEstadoDeLaRejilla();
    });
})(window.jQuery);
