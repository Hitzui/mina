/**
 * Las dos gráficas del panel de inicio.
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ LOS DATOS VIENEN EN EL HTML Y NO POR AJAX
 * ---------------------------------------------------------------------------
 *
 * Porque son dos series de doce meses y cuatro números, y ya vienen en la consulta
 * que hace la pantalla. Pedirlos por ajax sería un segundo viaje a la base para
 * pintar lo que el servidor acaba de calcular, y en un móvil con mala conexión
 * significa que el panel sale con las tarjetas llenas y un rectángulo en blanco
 * debajo hasta que la petición termina.
 *
 * El selector de periodo, en cambio, sí va por enlace y recarga la página. Y eso
 * es a propósito: es un enlace del servidor, funciona sin javascript y su url se
 * puede compartir. Un filtro que solo cambia con javascript no funciona si el
 * javascript no carga.
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ HAY UNA COMPROBACIÓN DE QUE HAY DATOS ANTES DE DIBUJAR
 * ---------------------------------------------------------------------------
 *
 * Porque ApexCharts con doce ceros y cuatro categorías en cero dibuja un eje
 * vacío con un círculo en el medio, que se lee como "el panel va roto". Con todos
 * los valores a cero lo que se pone es un texto que dice que en ese periodo no hay
 * nada. Es un caso real: el taller tiene todo lo registrado de septiembre y si se
 * abre el panel en octubre, sin esta comprobación se ve un círculo vacío.
 *
 * Y la comprobación es "todos a cero" y no "ninguno a cero", porque un mes con
 * gasto y sin ingreso es un dato —se está gastando y no entrando— y ese caso sí
 * tiene que dibujarse: es el que hace útil la gráfica.
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ NINGÚN NÚMERO SE FORMATEA AQUÍ
 * ---------------------------------------------------------------------------
 *
 * Porque los números de este panel se formatean en el servidor, con el formato de
 * Nicaragua: coma en los miles y punto en los decimales, 1,546.00. Si ApexCharts
 * los formateara por su cuenta —que es lo que hace por defecto, con el formato del
 * navegador— saldrían con el separador del revés respecto a las tarjetas de al lado
 * y de las tablas de la aplicación.
 *
 * El separador de miles se le pasa como etiqueta de eje formateada a mano, que es
 * un poco mas de codigo que dejar el automatico y a cambio las dos mitades del
 * panel dicen lo mismo.
 */

(function () {
    'use strict';

    /**
     * Un numero con el formato de Nicaragua: 1,546.00.
     *
     * La coma en los miles y el punto en los decimales. Es el mismo criterio que
     * number_format a secas en PHP, que es como lo escriben todas las tablas de la
     * aplicacion.
     */
    function numero(valor, decimales) {
        const n = Number(valor);

        if (!isFinite(n)) {
            return '0';
        }

        return n.toLocaleString('en-US', {
            minimumFractionDigits: decimales || 0,
            maximumFractionDigits: decimales || 0,
        });
    }

    /**
     * Lee el json de un atributo data-.
     *
     * Se envuelve en un try porque si el atributo viniera mal formado —por ejemplo,
     * si alguien lo editó a mano en el html de la plantilla— lo que no puede pasar
     * es que la página entera deje de pintar. Un panel que no enseña la gráfica
     * todavía enseña las cifras, que están en html y no dependen de esto.
     */
    function leer(atributo) {
        const elemento = document.querySelector('[data-panel-' + atributo + ']');

        if (!elemento) {
            return null;
        }

        const bruto = elemento.getAttribute('data-panel-' + atributo);

        if (!bruto) {
            return null;
        }

        try {
            return JSON.parse(bruto);
        } catch (e) {
            return null;
        }
    }

    /**
     * Si ApexCharts está en la página.
     *
     * Se comprueba antes de dibujar y no se asume: la librería es un archivo
     * estático que se sirve desde la carpeta de la plantilla, y si alguien la
     * borra o cambia la ruta, lo que tiene que pasar es que no se pinte la
     * gráfica y no que la página deje de funcionar. El mensaje "cargando" que hay
     * dentro del div se queda, y por eso el texto que se pone al principio dice
     * "cargando" y no "no se pudo cargar".
     */
    function hayApex() {
        return typeof window.ApexCharts === 'function';
    }

    /**
     * Sustituye el texto de "cargando" por uno que explica lo que pasa.
     */
    function sinGrafica(elemento, texto) {
        if (!elemento) {
            return;
        }

        elemento.innerHTML = '';

        const parrafo = document.createElement('p');

        parrafo.className = 'text-secondary text-center py-4 mb-0';
        parrafo.style.fontSize = '.8rem';
        parrafo.textContent = texto;

        elemento.appendChild(parrafo);
    }

    // ==================================================================
    // La de órdenes por estado
    // ==================================================================

    function graficaDeEstados() {
        const contenedor = document.getElementById('graficaEstados');

        if (!contenedor) {
            return;
        }

        if (!hayApex()) {
            sinGrafica(contenedor, 'No se pudo cargar la gráfica. Las cifras de arriba están completas.');

            return;
        }

        const datos = leer('ordenes');

        if (!datos || !datos.length) {
            sinGrafica(contenedor, 'No hay datos para dibujar.');

            return;
        }

        /*
         * Solo las que tienen algo. Las cuatro categorias del catalogo de estados
         * salen siempre en las tarjetas de arriba, pero en la grafica una categoria
         * en cero es un trozo de anillo que no se ve y que empuja el resto para
         * que no se entienda cual es cual.
         */
        const conAlgo = datos.filter(function (uno) {
            return Number(uno.cuantas) > 0;
        });

        if (conAlgo.length === 0) {
            sinGrafica(contenedor, 'No hay órdenes en este periodo.');

            return;
        }

        const opciones = {
            chart: {
                type: 'donut',
                height: 250,
                fontFamily: 'inherit',
                toolbar: { show: false },
            },
            series: conAlgo.map(function (uno) {
                return Number(uno.cuantas);
            }),
            labels: conAlgo.map(function (uno) {
                return uno.texto;
            }),
            colors: conAlgo.map(function (uno) {
                return uno.hex;
            }),
            legend: {
                position: 'bottom',
                fontSize: '13px',
                labels: { colors: '#6b7280' },
                markers: { size: 6 },
            },
            dataLabels: {
                enabled: true,
                formatter: function (porcentaje) {
                    return Math.round(porcentaje) + '%';
                },
            },
            tooltip: {
                y: {
                    formatter: function (valor) {
                        return valor + (valor === 1 ? ' orden' : ' órdenes');
                    },
                },
            },
            /*
             * El ancho de la leyenda se limita para que en un móvil no se parta en
             * cuatro lineas y empuje la gráfica hacia arriba. Con cuatro estados el
             * caso no llega a darse, pero el mismo componente pinta las de procesos
             * en cuanto se agrupe por etapa, y entonces sí.
             */
            stroke: { width: 2, colors: ['#fff'] },
        };

        const grafica = new ApexCharts(contenedor, opciones);

        grafica.render();
    }

    // ==================================================================
    // La de costo e ingreso por mes
    // ==================================================================

    function graficaDeMeses() {
        const contenedor = document.getElementById('graficaMeses');

        if (!contenedor) {
            return;
        }

        if (!hayApex()) {
            sinGrafica(contenedor, 'No se pudo cargar la gráfica. Las cifras de arriba están completas.');

            return;
        }

        const serie = leer('serie');

        if (!serie || !serie.etiquetas || !serie.etiquetas.length) {
            sinGrafica(contenedor, 'No hay datos para dibujar.');

            return;
        }

        const costo = (serie.costo || []).map(Number);
        const ingreso = (serie.ingreso || []).map(Number);

        const hayAlgo = costo.some(function (n) { return n > 0; })
            || ingreso.some(function (n) { return n > 0; });

        if (!hayAlgo) {
            sinGrafica(
                contenedor,
                'En los últimos doce meses no hay ningún costo ni ingreso registrado.'
            );

            return;
        }

        const opciones = {
            chart: {
                type: 'bar',
                height: 250,
                fontFamily: 'inherit',
                toolbar: { show: false },
                stacked: false,
            },
            series: [
                {
                    name: 'Costo',
                    data: costo,
                },
                {
                    name: 'Ingreso',
                    data: ingreso,
                },
            ],
            colors: ['#e7515a', '#00ab55'],
            plotOptions: {
                bar: {
                    columnWidth: '45%',
                    borderRadius: 3,
                },
            },
            dataLabels: {
                /*
                 * Apagadas, y es lo correcto para doce meses. Puestas, cada barra
                 * lleva su cifra encima y en un móvil se solapan unas con otras y
                 * no se lee ninguna. El número exacto sale pasando el ratón por la
                 * barra, y el total del periodo sale en las tarjetas de arriba.
                 */
                enabled: false,
            },
            stroke: { width: 1, colors: ['#fff'] },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '12px',
                labels: { colors: '#6b7280' },
                markers: { size: 6 },
            },
            xaxis: {
                categories: serie.etiquetas,
                labels: {
                    /*
                     * Los doce meses van inclinados, porque en un móvil no caben
                     * de pie y sin inclinar se montan unos encima de otros y no se
                     * lee ninguno. El rotate va en -45, que es el ángulo con el que
                     * la etiqueta entra en un hueco de la mitad de ancho sin
                     * invadir el de al lado.
                     */
                    rotate: -45,
                    style: { fontSize: '10px' },
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    /*
                     * El separador de miles va aqui, y no se deja el que pone
                     * ApexCharts por defecto, que es el del navegador. Es el unico
                     * sitio de este archivo donde sale un numero, y sale como
                     * 1,546 y no como 1.546: el taller es de Nicaragua.
                     */
                    formatter: function (valor) {
                        return numero(valor, 0);
                    },
                    style: { fontSize: '10px' },
                },
            },
            tooltip: {
                y: {
                    formatter: function (valor) {
                        return 'C$ ' + numero(valor, 2);
                    },
                },
            },
            grid: {
                borderColor: '#eef1f6',
                strokeDashArray: 3,
            },
        };

        const grafica = new ApexCharts(contenedor, opciones);

        grafica.render();
    }

    // ==================================================================
    // Arrancar
    // ==================================================================

    /**
     * Se dibuja cuando el DOM esta listo y no antes.
     *
     * El script va en el pie de la pagina, que es donde lo pone el layout, asi que
     * en theory el DOM ya estaria. Pero el layout tambien acepta scripts en la
     * cabeza, y si alguien mueve este archivo de sitio la pagina se queda sin
     * graficas sin decir nada. Un DOMContentLoaded cuesta nada y quita ese
     * depende.
     */
    function arrancar() {
        graficaDeEstados();
        graficaDeMeses();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }
})();
