{{--
    Los tipos de ingreso.

    Son cuatro y los puso el taller a mano en la base de datos, que se puede
    hacer pero es lo unico del sistema que se hace asi. Esta pantalla es para
    que se puedan corregir, desactivar y dar de baja sin salir de la aplicacion.

    Y NO SE DECIDE AQUI QUE TIPOS HAY. Ni se anaden ni se completan: los tipos
    que hay son los del taller y quien decide que falta es el que lleva el
    taller. Un maestro de tipos de ingreso es una opinion sobre como funciona
    el negocio, y poner opiniones distintas de las del taller es exactamente el
    error que hace que un software no sirva.

    La tabla esta ordenada por nombre y no por orden de alta, porque en un
    catalogo lo que se mira al abrir la pantalla es la lista de los nombres y
    esa tiene que estar en orden alfabetico.

    Y hay una columna que dice cuantos ingresos lleva cada tipo, que es lo que
    responde a la pregunta que uno se hace al mirar la fila: "este lo puedo
    borrar o no". Un tipo sin ingresos sale con un guion y uno con ingresos sale
    con el numero, de modo que la razon por la que el boton de borrar no va a
    funcionar esta escrita antes de pulsarlo, no despues.

    El boton de borrar no se esconde por eso. Podria, y la lista quedaria mas
    limpia, pero el que tiene que desactivar un tipo en vez de borrarlo no lo
    sabe todavia: solo lo sabria si el boton no estuviera, preguntandose por
    que. Con el boton ahi y el aviso al pulsarlo, ve la razon exacta y el numero
    de ingresos que seVERN afectada.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Tipos de ingreso' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        {{--
            Vacio a proposito, y declarado igualmente.

            El layout mete {{ $headerFiles }} en la cabeza sin preguntar antes
            si existe, asi que una pantalla que no declare el slot tumba la
            pagina entera con un "Undefined variable headerFiles". Todas las de
            la aplicacion lo declaran, vacio o no, y esta va igual: lo que no
            tiene es nada que cargar, porque aqui no hay select2 ni ningun otro
            archivo de los que van en la cabeza.
        --}}
    </x-slot:headerFiles>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

                    <div>
                        <h4 class="mb-1">
                            <i class="bi bi-cash-coin me-2"></i>
                            Tipos de ingreso
                        </h4>

                        <p class="text-muted mb-0">
                            Por qué entra dinero en una orden: servicio de
                            procesamiento, participación en oro, venta de oro y
                            los demás. Es el catálogo que se elige al registrar
                            un ingreso.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-primary"
                        id="btnNuevoTipoIngreso"
                    >
                        <i class="bi bi-plus me-1"></i>
                        Nuevo tipo
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
    @include('configuracion.tipos_ingreso._modal_form')

    {{-- Y la ficha, que tampoco necesita pantalla propia --}}
    @include('configuracion.tipos_ingreso._modal_ver')

    <x-slot:footerFiles>

        {!! $dataTable->scripts() !!}

        <script src="{{ asset('js/configuracion/tipos_ingreso.js') }}"></script>

    </x-slot>

</x-base-layout>
