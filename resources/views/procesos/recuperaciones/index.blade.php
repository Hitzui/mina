{{--
    El oro que sale del taller.

    Es una lista de fechas con unos gramos al lado, y cada registro se edita o
    se borra desde un modal sobre la lista. No hay pantalla aparte para uno
    suelto: seria una pantalla para ver un numero.

    La columna del estado de la orden no esta por adorno. Una recuperacion
    cuelga de una orden, y hay una regla que dice que una orden cerrada no
    admite recuperaciones nuevas pero si admite corregirlas —es la misma regla
    de los costos, los trabajos y los equipos—. Ver el estado al lado de cada
    fila es lo que hace que esa regla se entienda sin abrir la orden: si la de
    arriba esta finalizada, esa se puede tocar pero no se pueden anadir mas
    debajo.

    Y va en Produccion, y no dentro de la ficha de la orden, porque no es un
    dato del taller sino un hecho economico: los gramos de una orden se
    pueden mirar todos juntos, que es como se mira una produccion, y ademas
    una orden puede tener varias recuperaciones —una por partida— que en una
    pantalla global se ven todas.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Recuperaciones de oro' }}
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
                            Recuperaciones de oro
                        </h4>

                        <p class="text-muted mb-0">
                            Los gramos de oro que salieron del taller en cada
                            partida, y con qué pureza. Es lo que se multiplica
                            después por el precio del gramo de ese día para
                            saber cuánto valió.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-primary"
                        id="btnNuevaRecuperacion"
                    >
                        <i class="bi bi-plus me-1"></i>
                        Nueva recuperación
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
    @include('procesos.recuperaciones._modal_form')

    <x-slot:footerFiles>

        {!! $dataTable->scripts() !!}

        @vite(['resources/assets/js/select2/select2-init.js'])

        <script src="{{ asset('js/procesos/recuperaciones.js') }}"></script>

    </x-slot>

</x-base-layout>
