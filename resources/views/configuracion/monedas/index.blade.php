{{--
    El catalogo de monedas.

    Son cinco campos y la lista cabe entera en la vista de una vez, asi que
    todo pasa aqui: no hay pantallas de alta ni de edicion, sino modales. Una
    pantalla aparte para escribir "EUR" y "Euros" seria mas sitio en blanco
    que otra cosa.

    La tabla esta ordenada por codigo, que es como se lee de un vistazo, y no
    por orden de alta, que no dice nada.

    El boton de nueva moneda esta arriba a la derecha y no dentro de la tabla,
    porque es la accion de la pantalla y no la de una fila: se llega aqui
    para anadir una moneda, no para anadir una en concreto.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Monedas' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        {{--
            Vacio a proposito, y declarado igualmente.

            El layout mete {{ $headerFiles }} en la cabeza sin preguntar antes
            si existe, asi que una pantalla que no declare el slot tumba la
            pagina entera con un "Undefined variable headerFiles". Todas las
            de la aplicacion lo declaran, vacio o no, y esta va igual: lo que
            no tiene es nada que cargar, porque aqui no hay select2 ni ningun
            otro archivo de los que van en la cabeza.
        --}}
    </x-slot:headerFiles>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

                    <div>
                        <h4 class="mb-1">
                            <i class="bi bi-currency-exchange me-2"></i>
                            Monedas
                        </h4>

                        <p class="text-muted mb-0">
                            Las monedas con las que se compra y se lleva la
                            contabilidad. La que está marcada como
                            <strong>base</strong> es en la que está el taller:
                            todo lo que se registra en otra moneda se convierte
                            a esa con el tipo de cambio del día.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-primary"
                        id="btnNuevaMoneda"
                    >
                        <i class="bi bi-plus me-1"></i>
                        Nueva moneda
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
    @include('configuracion.monedas._modal_form')

    {{-- Y la ficha, que tampoco necesita pantalla propia --}}
    @include('configuracion.monedas._modal_ver')

    <x-slot:footerFiles>

        {!! $dataTable->scripts() !!}

        <script src="{{ asset('js/configuracion/monedas.js') }}"></script>

    </x-slot>

</x-base-layout>
