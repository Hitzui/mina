{{--
    El panel de inicio.

    ===========================================================================
    COMO ESTA ORDENADO Y POR QUE EN ESTE ORDEN
    ===========================================================================

    De arriba abajo va lo que se lee de un vistazo a lo que se lee con pinza:

      1. Las cifras del periodo. Lo primero que uno mira, y lo que decide si baja
         del todo o sigue leyendo.

      2. Los procesos por estado. Va con las cifras y no con las listas porque es
         una cifra tambien, y porque es la que contesta la pregunta que mas se
         hace al abrir el taller: ¿cuántas cosas hay empezadas?

      3. El dinero y el oro. Va aparte de las cuentas de ordenes porque no se
         cuentan igual: unas son un numero de filas y estas son sumas de dinero. Y
         la tarjeta del costo lleva las cuatro partes debajo, que es lo que
         permite entender de donde sale el total sin abrir otra pantalla.

      4. Las gráficas. Debajo de los numeros y no al lado, porque una gráfica se
         mira después de saber quédice el número. Al revés, el número se mira
         después de entender la gráfica, y entonces ya no se mira.

      5. Las listas. Al final, porque son lo que se va a leer fila a fila y eso es
         lo último que se hace en un panel.

    ===========================================================================
    Y POR QUE HAY UNA LÍNEA QUE DICE QUE LAS LISTAS NO FILTRAN
    ===========================================================================

    Porque es la diferencia que más confunde si no se dice. Las cifras de arriba son
    de "este mes" y las listas de abajo son de ahora mismo. Al cambiar el periodo
    las cifras cambian y las listas no, y sin una línea que lo diga parece un fallo.

    La línea está porque el taller va a cambiar el periodo un día, ver que las
    listas no se mueven, y pensar que el panel está roto. Que avise es más barato
    que un parte de "el filtro no funciona".

    ===========================================================================
    EL SELECTOR DE PERIODO
    ===========================================================================

    Son enlaces y no un <select> con javascript, por dos razones: funciona sin
    javascript —que en un móvil con mala conexión es la mitad de las veces— y la
    url se puede compartir. El enlace "el panel de este año" es una url: se copia
    y se manda a quien lo quiera mirar.

    ===========================================================================
    LOS NÚMEROS VIENEN FORMATEADOS DESDE EL SERVIDOR
    ===========================================================================

    Con number_format a secas, que es lo que usan las 124 llamadas del proyecto y
    por lo tanto todas las tablas: coma en los miles y punto en los decimales,
    1,546.00. El taller es de Nicaragua.

    Y no se formatea en javascript, porque entonces habría dos formateadores y el
    día que uno cambiara el otro no. Esa decision ya se tomó una vez en el proyecto
    y está escrita en los seis archivos de javascript que hacen números.

    ===========================================================================
    LOS GRÁFICOS
    ===========================================================================

    Van con ApexCharts, que ya está en la plantilla, y sus datos viajan en el json
    del html con la etiqueta data-panel-serie. No se piden por ajax porque son dos
    series de doce meses: pedirlas por ajax sería un viaje a la base para pintar
    lo que ya se sabe al enviar la página.

    Y hay un caso que hay que tener en cuenta: si ApexCharts no carga, el panel no
    se rompe. Las cifras están todas en tarjetas, que son html, y la gráfica es lo
    único que se pierde. Por eso el contenedor de la gráfica lleva un texto de
    "cargando" que se queda si el javascript nunca llega.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>Panel</x-slot>
    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        <link rel="stylesheet" href="{{ asset('plugins/apex/apexcharts.css') }}">

        <style>
            /*
             * El css de las tres listas, y no una clase mas.
             *
             * Cada fila lleva su linea de abajo y lo que se quiere es que la ultima
             * no la lleve, para que el borde no toque el borde de la tarjeta. La
             * clase "last-child-border-0" fue lo primero que se puso: no existe en
             * la hoja del tema, asi que la ultima fila salia con una linea cruzando
             * el fondo de la tarjeta.
             *
             * Y va aqui y no en una hoja aparte porque son tres lineas y solo las
             * necesita esta pantalla. El selector es ".panel-lista li:last-child",
             * que es el gancho que pone la lista.
             */
            .panel-lista li:last-child {
                border-bottom: 0 !important;
            }
        </style>
    </x-slot>

    <div class="row layout-top-spacing">

        {{-- ============================================================
             LA CABECERA Y EL SELECTOR DE PERIODO
             ============================================================ --}}

        <div class="col-12">

            <div class="card br-8 mb-3">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">

                        <div>
                            <h4 class="mb-1 fw-bold" style="font-size: 1.15rem;">Panel</h4>
                            <p class="text-secondary mb-0" style="font-size: .78rem;">
                                Cifras de <strong>{{ $periodoNombre }}</strong>,
                                hasta el {{ now()->format('d/m/Y') }}.
                            </p>
                        </div>

                        <div class="btn-group btn-group-sm" role="group" aria-label="Periodo">

                            @foreach ($botonesPeriodo as $boton)
                                <a href="{{ $boton['url'] }}"
                                   class="btn {{ $boton['activo'] ? 'btn-primary' : 'btn-outline-secondary' }}"
                                   @if ($boton['activo']) aria-current="page" @endif>
                                    {{ $boton['texto'] }}
                                </a>
                            @endforeach

                        </div>

                    </div>
                </div>
            </div>

        </div>

        {{-- ============================================================
             1. LAS ÓRDENES POR ESTADO
             ============================================================ --}}

        <div class="col-12">
            <p class="text-uppercase text-secondary fw-semibold mb-2"
               style="font-size: .72rem; letter-spacing: .05em;">
                Órdenes de {{ mb_strtolower($periodoNombre) }}
            </p>
        </div>

        {{--
            Las cuatro tarjetas de órdenes salen de un bucle y no están escritas una a
            una, y no por ahorrar líneas: porque el color de la letra lo decide el
            hexadecimal del estado, y si el color de fondo y el hexadecimal van en
            sitios distintos acabarían siendo distintos el uno del otro y la tarjeta
            se pinta de un color mientras el número se calcula pensando en otro.
            Con un solo sitio no pueden separarse.

            Y el título no sale de "texto" más una "s", porque "En proceso" más "s" es
            "En procesos", que no existe. Los títulos van escritos en el servicio, en
            plural y en las palabras del taller.
        --}}
        @foreach ($ordenesPorEstado as $estado)
            <div class="col-6 col-md-4 col-xl-3">
                <x-dashboard._tarjeta
                    :titulo="$estado['titulo']"
                    :valor="(string) $estado['cuantas']"
                    :hex="$estado['hex']"
                    :color="$estado['color']"
                    :icono="$estado['icono']"
                    :pie="$estado['pie']"
                    :enlace="route('procesos.ordenes_trabajo.index', ['estado' => $estado['estado']])" />
            </div>
        @endforeach

        {{-- ============================================================
             2. LOS PROCESOS
             ============================================================ --}}

        <div class="col-12">
            <p class="text-uppercase text-secondary fw-semibold mb-2 mt-1"
               style="font-size: .72rem; letter-spacing: .05em;">
                Procesos empezados en {{ mb_strtolower($periodoNombre) }}
            </p>
        </div>

        @foreach ($procesosPorEstado as $procesoEstado)
            <div class="col-6 col-md-3">
                <x-dashboard._tarjeta
                    :titulo="$procesoEstado['texto']"
                    :valor="(string) $procesoEstado['cuantas']"
                    :hex="$procesoEstado['hex']"
                    :color="$procesoEstado['color']"
                    icono="bi-diagram-3"
                    :pie="$procesoEstado['pie']" />
            </div>
        @endforeach

        {{-- ============================================================
             3. EL DINERO Y EL ORO
             ============================================================ --}}

        <div class="col-12">
            <p class="text-uppercase text-secondary fw-semibold mb-2 mt-1"
               style="font-size: .72rem; letter-spacing: .05em;">
                Dinero de {{ mb_strtolower($periodoNombre) }}
            </p>
        </div>

        {{--
            Las tres de dinero llevan su hexadecimal al lado de la clase de bootstrap.

            Y no salen del modelo porque no son estados de nada: son colores para
            distinguir tres cifras que sí son estados —lo que sale, lo que entra y lo
            que se recuperó— y no hay ningún catálogo que los defina. Los
            hexadecimales son los del tema para esas clases, y hay un test que lo
            comprueba contra la hoja de estilos: si el tema cambiara el rojo, el test
            falla en vez de que la tarjeta se pintara de un color y la letra se
            calculara pensando en otro.
        --}}

        <div class="col-12 col-lg-4">
            <x-dashboard._tarjeta
                titulo="Costo total"
                :valor="'C$ ' . number_format($dinero['costo_total'], 2)"
                icono="bi-arrow-down-circle"
                color="bg-danger"
                hex="#e7515a"
                :pie="'En ' . $dinero['costo_filas'] . ' '
                    . ($dinero['costo_filas'] === 1 ? 'fila' : 'filas')
                    . ': ' . number_format($dinero['partes']['costos']['total'], 2)
                    . ' en costos + ' . number_format($dinero['partes']['mano_de_obra']['total'], 2)
                    . ' en mano de obra + ' . number_format($dinero['partes']['depreciacion']['total'], 2)
                    . ' en uso de equipos + ' . number_format($dinero['partes']['materia_prima']['total'], 2)
                    . ' en materia prima'"
                :aviso="$dinero['costo_sin_equivalente'] > 0
                    ? $dinero['costo_sin_equivalente']
                        . ' '
                        . ($dinero['costo_sin_equivalente'] === 1 ? 'fila quedó' : 'filas quedaron')
                        . ' sin tipo de cambio y no suma en esta cifra'
                    : null" />
        </div>

        <div class="col-12 col-lg-4">
            <x-dashboard._tarjeta
                titulo="Ingreso facturado"
                :valor="'C$ ' . number_format($dinero['ingresos'], 2)"
                icono="bi-arrow-up-circle"
                color="bg-success"
                hex="#00ab55"
                :pie="'En ' . $dinero['ingresos_filas'] . ' '
                    . ($dinero['ingresos_filas'] === 1 ? 'ingreso' : 'ingresos')"
                :aviso="$dinero['ingresos_sin_equivalente'] > 0
                    ? $dinero['ingresos_sin_equivalente']
                        . ' '
                        . ($dinero['ingresos_sin_equivalente'] === 1 ? 'ingreso quedó' : 'ingresos quedaron')
                        . ' sin tipo de cambio y no suma en esta cifra'
                    : null" />
        </div>

        <div class="col-12 col-lg-4">
            <x-dashboard._tarjeta
                titulo="Oro recuperado"
                :valor="number_format($oro['total'], 4) . ' g'"
                icono="bi-gem"
                color="bg-info"
                hex="#2196f3"
                :pie="$oro['filas'] . ' '
                    . ($oro['filas'] === 1 ? 'partida' : 'partidas')
                    . ($oro['sinPureza'] > 0
                        ? ' · ' . $oro['sinPureza'] . ' sin medir la pureza'
                        : '')
                    . ($oro['finos'] > 0
                        ? ' · ' . number_format($oro['finos'], 4) . ' g finos'
                        : '')" />
        </div>

        {{--
            Y el margen NO aparece, y no es un olvido. Lo que entra y lo que sale
            son números de sistemas distintos: la mano de obra no se le cobra al
            cliente como un porcentaje del oro, y restarlos daría un margen que en
            este taller no significa nada. Está escrito en PanelDelTaller.
        --}}

        {{-- ============================================================
             4. LAS GRÁFICAS
             ============================================================ --}}

        <div class="col-12 col-lg-5">
            <div class="card br-8 mb-3 h-100">
                <div class="card-header py-3 px-3 border-bottom">
                    <h6 class="mb-0 fw-semibold" style="font-size: .9rem;">
                        Órdenes por estado
                    </h6>
                    <p class="text-secondary mb-0 mt-1" style="font-size: .72rem;">
                        {{ mb_strtolower($periodoNombre) }}
                    </p>
                </div>

                <div class="card-body">
                    {{--
                        El div va vacío y con el texto dentro, y el texto se queda si
                        ApexCharts no carga. Un contenedor de gráfica vacío es un
                        rectángulo en blanco del tamaño equivocado, que se lee como
                        un fallo de la pantalla.
                    --}}
                    <div id="graficaEstados" data-panel-ordenes="{{ $jsonEstados }}">
                        <p class="text-secondary text-center py-4 mb-0" style="font-size: .8rem;">
                            Cargando la gráfica…
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card br-8 mb-3 h-100">
                <div class="card-header py-3 px-3 border-bottom">
                    <h6 class="mb-0 fw-semibold" style="font-size: .9rem;">
                        Costo e ingreso por mes
                    </h6>
                    <p class="text-secondary mb-0 mt-1" style="font-size: .72rem;">
                        Últimos doce meses, en córdobas. No cambia con el periodo de
                        arriba porque es una tendencia.
                    </p>
                </div>

                <div class="card-body">
                    <div id="graficaMeses" data-panel-serie="{{ $jsonSerie }}">
                        <p class="text-secondary text-center py-4 mb-0" style="font-size: .8rem;">
                            Cargando la gráfica…
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================
             5. LAS LISTAS
             ============================================================ --}}

        <div class="col-12">

            <p class="text-uppercase text-secondary fw-semibold mb-1 mt-1"
               style="font-size: .72rem; letter-spacing: .05em;">
                Para hoy
            </p>

            <p class="text-secondary mb-3" style="font-size: .75rem;">
                Estas tres listas <strong>no</strong> cambian con el periodo de
                arriba: son de ahora mismo.
            </p>

        </div>

        <div class="col-12 col-lg-4">
            <x-dashboard._lista
                titulo="Procesos atrasados"
                :items="$procesosAtrasados"
                fila="dashboard.filas._procesoAtrasado"
                vacio="Nada se quedó a medias."
                icono="bi-exclamation-triangle"
                colorIcono="bg-warning text-dark"
                :maximo="6"
                pie="Procesos con fecha de inicio y sin fecha de fin. El número es
                     los días que llevan así." />
        </div>

        <div class="col-12 col-lg-4">
            <x-dashboard._lista
                titulo="Órdenes abiertas"
                :items="$ordenesAbiertas"
                fila="dashboard.filas._ordenAbierta"
                vacio="No hay ninguna orden pendiente ni en proceso."
                icono="bi-folder2-open"
                colorIcono="bg-primary"
                :maximo="6" />
        </div>

        <div class="col-12 col-lg-4">
            <x-dashboard._lista
                titulo="Últimas órdenes"
                :items="$ultimasOrdenes"
                fila="dashboard.filas._ultimaOrden"
                vacio="Todavía no se ha creado ninguna orden."
                icono="bi-clock-history"
                colorIcono="bg-secondary"
                :maximo="5" />
        </div>

    </div>

    <x-slot:footerFiles>
        <script src="{{ asset('plugins/apex/apexcharts.min.js') }}"></script>
        <script src="{{ asset('js/dashboard/panel.js') }}"></script>
    </x-slot>

</x-base-layout>
