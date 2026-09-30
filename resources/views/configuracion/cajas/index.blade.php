{{--
    Las cajas.

    Son las cuentas donde entra el dinero cuando el cliente paga: la caja chica
    de la recepcion, el fondo con que trabaja el taller, el efectivo del vehiculo
    que va a buscar el oro. La pantalla es para que se puedan crear, corregir,
    desactivar y dar de baja sin salir de la aplicacion.

    Y AQUI SI SE PUEDE CREAR, al reves que en los tipos de ingreso. Alli los
    cuatro tipos los puso el taller a mano y la pantalla no los anade, porque son
    una clasificacion del gasto y quien decide eso es el taller. Una caja no es
    eso: es un hecho. El taller tiene o no tiene caja chica, y el dia que la
    tiene se da de alta. Ademas la tabla estaba vacia, y una pantalla que solo
    puede corregir filas que no existen se abre y no hace nada.

    La tabla esta ordenada por nombre y no por orden de alta, porque en un
    catalogo lo que se mira al abrir la pantalla es la lista de los nombres y
    esa tiene que estar en orden alfabetico.

    Y hay una columna que dice cuantos cobros lleva cada caja, que es lo que
    responde a la pregunta que uno se hace al mirar la fila: "esta la puedo
    borrar o no". Una caja sin cobros sale con un guion y una con cobros sale con
    el numero, de modo que la razon por la que el boton de borrar no va a
    funcionar esta escrita antes de pulsarlo, no despues.

    El boton de borrar no se esconde por eso. Podria, y la lista quedaria mas
    limpia, pero el que tiene que desactivar una caja en vez de borrarla no lo
    sabe todavia: solo lo sabria si el boton no estuviera, preguntandose por que.
    Con el boton ahi y el aviso al pulsarlo, ve la razon exacta y el numero de
    cobros que se VERAN afectados.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Cajas' }}
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
                            <i class="bi bi-safe2 me-2"></i>
                            Cajas
                        </h4>

                        <p class="text-muted mb-0">
                            Las cuentas donde entra el dinero de los cobros: la
                            caja chica, el fondo del taller, el efectivo del
                            vehículo. Es el catálogo que se elige al registrar un
                            cobro.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-primary"
                        id="btnNuevaCaja"
                    >
                        <i class="bi bi-plus me-1"></i>
                        Nueva caja
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
    @include('configuracion.cajas._modal_form')

    {{-- Y la ficha, que tampoco necesita pantalla propia --}}
    @include('configuracion.cajas._modal_ver')

    <x-slot:footerFiles>

        {!! $dataTable->scripts() !!}

        <script src="{{ asset('js/configuracion/cajas.js') }}"></script>

    </x-slot>

</x-base-layout>
