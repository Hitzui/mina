{{--
    La serie del tipo de cambio.

    Es una lista de fechas con un numero al lado, y cada dia se edita o se
    borra desde un modal sobre la propia lista. No hay pantalla aparte para
    un dia suelto: seria una pantalla para ver un numero.

    El boton de importar el mes va al lado del de nuevo. Son las dos formas
    de meter lo mismo, y quien llega aqui con el Excel del banco lleno de
    dias no deberia tener que buscar el boton pequeno.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Tipo de cambio' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        {{--
            Select2, para el selector de moneda. Sin el buscador, con el
            catalogo de monedas entero por delante habia que recorrerlo.
        --}}
        @vite(['resources/scss/light/plugins/select2/custom-select2.scss'])
    </x-slot:headerFiles>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

                    <div>
                        <h4 class="mb-1">
                            <i class="bi bi-cash-coin me-2"></i>
                            Tipo de cambio
                        </h4>

                        <p class="text-muted mb-0">
                            Cuántos córdobas vale una unidad de la moneda
                            cada día. Las compras en dólares y el material que
                            entra al almacén se valúan con esto.
                        </p>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button
                            type="button"
                            class="btn btn-outline-success"
                            id="btnImportarTipoCambioAbrir"
                        >
                            <i class="bi bi-file-earmark-excel me-1"></i>
                            Importar el mes
                        </button>

                        <button
                            type="button"
                            class="btn btn-primary"
                            id="btnNuevoTipoCambio"
                        >
                            <i class="bi bi-plus me-1"></i>
                            Nuevo tipo de cambio
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
    @include('configuracion.tipos_cambio._modal_form')

    {{-- Y la importacion del mes tambien, que son tres campos y un archivo --}}
    @include('configuracion.tipos_cambio._modal_importar')

    <x-slot:footerFiles>

        {!! $dataTable->scripts() !!}

        @vite(['resources/assets/js/select2/select2-init.js'])

        <script src="{{ asset('js/configuracion/tipos_cambio.js') }}"></script>

    </x-slot>

</x-base-layout>
