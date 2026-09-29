{{--
    La serie del precio del oro.

    Es una lista de fechas con un numero al lado, y cada dia se edita o se
    borra desde un modal sobre la propia lista. No hay pantalla aparte para un
    dia suelto: seria una pantalla para ver un numero.

    Esta pantalla es la que da sentido a valoraciones_oro. Ahi se dice cuantos
    gramos salieron del taller, y para saber cuanto valen hace falta el precio
    de un gramo ese dia. Sin esta serie, esa valoracion no tendria con que
    multiplicar.

    El boton de importar el mes va al lado del de nuevo. Son las dos formas de
    meter lo mismo, y quien llega aqui con el archivo del mes lleno de dias no
    deberia tener que buscar el boton pequeno.

    Y va aparte del tipo de cambio a proposito. Los dos son series por dias y
    los dos se cargan desde Excel, pero no son lo mismo: aqui la unidad es el
    gramo y no una moneda, y el cero significa "no se sabe el precio" mientras
    que en el tipo de cambio el cero no se admite. Meter las dos en la misma
    tabla daria dos sitios donde mirar el precio del oro.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Precios del oro' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        @vite(['resources/scss/light/plugins/select2/custom-select2.scss'])
    </x-slot:headerFiles>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

                    <div>
                        <h4 class="mb-1">
                            <i class="bi bi-gem me-2"></i>
                            Precios del oro
                        </h4>

                        <p class="text-muted mb-0">
                            Cuánto valía un gramo de oro cada día. Es con lo
                            que se valora lo que sale del taller: una
                            recuperación dice cuántos gramos hubo, y aquí está
                            el precio con el que se multiplican.
                        </p>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button
                            type="button"
                            class="btn btn-outline-success"
                            id="btnImportarPrecioOroAbrir"
                        >
                            <i class="bi bi-file-earmark-excel me-1"></i>
                            Importar el mes
                        </button>

                        <button
                            type="button"
                            class="btn btn-primary"
                            id="btnNuevoPrecioOro"
                        >
                            <i class="bi bi-plus me-1"></i>
                            Nuevo precio
                        </button>
                    </div>

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
    @include('configuracion.precios_oro._modal_form')

    {{-- Y la importacion del mes tambien, que son tres campos y un archivo --}}
    @include('configuracion.precios_oro._modal_importar')

    <x-slot:footerFiles>

        {!! $dataTable->scripts() !!}

        @vite(['resources/assets/js/select2/select2-init.js'])

        <script src="{{ asset('js/configuracion/precios_oro.js') }}"></script>

    </x-slot>

</x-base-layout>
