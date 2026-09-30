{{--
    Cuanto vale lo que salio del taller.

    Es una lista de fechas con una cifra al lado, y cada registro se edita o se
    borra desde un modal sobre la lista. No hay pantalla aparte para uno suelto:
    seria una pantalla para ver un numero.

    LO QUE ESTA EN LA LISTA Y POR QUE.

    Los gramos valorados van en su propia columna y no son los de la
    recuperacion: si la partida dio 2 gramos al 75 %, lo que se valoro fueron
    1,5. Enseñar los 2 al lado de un valor hecho con 1,5 deja la duda de si la
    cuenta esta mal, asi que la cifra que se multiplico va explicita, con la
    partida al lado en letra pequena.

    Y el valor lleva su moneda, porque 7.050 en cordoba y 7.050 en dolares no
    son lo mismo y un numero suelto no dice de que.

    El precio del gramo va con el dia del que salio, y solo lo pone si es otro
    distinto al de la valoracion. Cuando coincide, decirlo es ruido; cuando no
    coincide, es justo lo primero que hay que mirar si un valor parece alto: se
    Cerro con el precio de un dia en el que el banco no cotizo.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Valoraciones de oro' }}
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
                            <i class="bi bi-calculator me-2"></i>
                            Valoraciones de oro
                        </h4>

                        <p class="text-muted mb-0">
                            Cuánto valió cada partida de oro que salió del taller.
                            La cuenta la hace el sistema con los gramos de la
                            recuperación y el precio del gramo de ese día; aquí
                            no se escribe ninguna cifra a mano.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-primary"
                        id="btnNuevaValoracion"
                    >
                        <i class="bi bi-plus me-1"></i>
                        Nueva valoración
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
    @include('procesos.valoraciones_oro._modal_form')

    <x-slot:footerFiles>

        {!! $dataTable->scripts() !!}

        @vite(['resources/assets/js/select2/select2-init.js'])

        <script src="{{ asset('js/procesos/valoraciones_oro.js') }}"></script>

    </x-slot>

</x-base-layout>
