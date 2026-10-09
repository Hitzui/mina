{{--
    Los metodos de pago.

    Como recibe el taller el dinero de un cobro: efectivo, transferencia, targeta,
    el talon con que se cobra despues. Es el catalogo que se elige al registrar
    un cobro.

    NO SON LOS TIPOS DE PAGO DE EMPLEADO, que son otra cosa. Aquellos son como se
    le paga a la gente que trabaja aqui; estos son como se le paga a quien compra.
    Las dos pantallas se parecen porque las dos son catalogos de formas de pago y
    estan en el mismo bloque de Configuracion, pero las filas que meten no se
    mezclan, y por eso son dos tablas y no una con un campo que las separe.

    Y AQUI SE PUEDE CREAR, y eso no es una excepcion. Los tipos de ingreso no se
    anaden, porque son una clasificacion de como entra el dinero y eso lo decide
    el taller, que los puso a mano en la base. El precedente mas cercano esta en
    el mismo proyecto: los tipos de pago de empleado —que son exactamente la
    misma idea, como se paga una cosa— tienen pantalla con alta, y las seis filas
    que hay las metio el taller una a una desde ella. Un catalogo de formas de
    cobrar es una lista de hechos del taller, no una teoria sobre como se lleva
    la contabilidad: el taller acepta efectivo y transferencia, o acepta targetas,
    y la pantalla tiene que poder recoger lo que contourne. Ademas la tabla esta
    vacia, y una pantalla que solo puede corregir y desactivar filas que no existen
    se abre y no hace nada.

    La tabla esta ordenada por nombre y no por orden de alta, porque en un
    catalogo lo que se mira al abrir la pantalla es la lista de los nombres y
    esa tiene que estar en orden alfabetico.

    Y hay una columna que dice cuantos cobros lleva cada metodo, que es lo que
    responde a la pregunta que uno se hace al mirar la fila: "este lo puedo
    borrar o no". Un metodo sin cobros sale con un guion y uno con cobros sale con
    el numero, de modo que la razon por la que el boton de borrar no va a
    funcionar esta escrita antes de pulsarlo, no despues.

    El boton de borrar no se esconde por eso. Podria, y la lista quedaria mas
    limpia, pero el que tiene que desactivar un metodo en vez de borrarlo no lo
    sabe todavia: solo lo sabria si el boton no estuviera, preguntandose por que.
    Con el boton ahi y el aviso al pulsarlo, ve la razon exacta y el numero de
    cobros que se VERAN afectados.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Métodos de pago' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        {{--
            Vacio a proposito, y declarado igualmente.

            El layout mete {{ $headerFiles }} en la cabeza sin preguntar antes si
            existe, asi que una pantalla que no declare el slot tumba la pagina
            entera con un "Undefined variable headerFiles". Todas las de la
            aplicacion lo declaran, vacio o no, y esta va igual: lo que no tiene
            es nada que cargar, porque aqui no hay select2 ni ningun otro archivo
            de los que van en la cabeza.
        --}}
    </x-slot:headerFiles>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

                    <div>
                        <h4 class="mb-1">
                            <i class="bi bi-credit-card-2-front me-2"></i>
                            Métodos de pago
                        </h4>

                        <p class="text-muted mb-0">
                            Cómo recibe el taller el dinero de un cobro: efectivo,
                            transferencia, tarjeta, el talon con que se cobra
                            después. Es el catálogo que se elige al registrar un
                            cobro.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-primary"
                        id="btnNuevoMetodoPago"
                    >
                        <i class="bi bi-plus me-1"></i>
                        Nuevo método
                    </button>

                </div>

                <div class="table-responsive">

                    {!! $dataTable->table([
                        'class' => 'table table-hover table-bordered w-100',
                    ]) !!}

                </div>

            </div>

        </div>

    </div>

    {{-- El alta y la edicion van en modal, sobre la lista --}}
    @include('configuracion.metodos_pago._modal_form')

    {{-- Y la ficha, que tampoco necesita pantalla propia --}}
    @include('configuracion.metodos_pago._modal_ver')

    <x-slot:footerFiles>

        {!! $dataTable->scripts() !!}

        <script src="{{ asset('js/configuracion/metodos_pago.js') }}"></script>

    </x-slot>

</x-base-layout>
