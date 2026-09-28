{{--
    Pantalla de alta de una compra.

    El formulario va en _form, que es el mismo para alta y edicion: lo que
    cambia entre las dos son los botones y la accion, y duplicar la pantalla
    entera haria que corregir una cosa en un sitio se olvidara en el otro.
--}}
<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Nueva compra' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        {{--
            Select2: los estilos.

            Sin esto el material de cada linea era un <select> nativo, o sea
            una lista desplegable del sistema sin buscador. Con el catalogo de
            materiales encima, buscar uno a mano era recorrerla entera. El
            select2 lo pone el javascript de abajo; aqui solo su cascara.
        --}}
        @vite(['resources/scss/light/plugins/select2/custom-select2.scss'])
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-10 col-lg-12 col-sm-12 layout-spacing">

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">
                        <i class="bi bi-bag-plus me-1"></i>
                        Nueva compra
                    </h4>

                    <p class="text-muted mb-0">
                        El código lo pone el sistema y los totales los suman las
                        líneas. Al finalizar la compra, el material entra al
                        almacén.
                    </p>
                </div>
            </div>

            {{-- Los errores que vienen de la validacion se muestran aqui
                 arriba del formulario, no en cada campo: los de "la compra
                 tiene que llevar al menos una linea" y los de "no se puede
                 deshacer" no pertenecen a un campo concreto. --}}
            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <h6 class="alert-heading">Hay algo que corregir</h6>
                    <ul class="mb-0">
                        @foreach($errors->all() as $mensaje)
                            <li>{{ $mensaje }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="widget-content widget-content-area br-8">

                @include('inventario.compras._form')

            </div>

        </div>

    </div>

    {{--
        El modal de elegir proveedor va fuera del formulario, a proposito: un
        formulario dentro de un modal dentro de un formulario es un formulario
        anidado, y el navegador no sabe a cual de los dos pertenece un campo.
    --}}
    @include('inventario.compras._modal_selector_proveedor')

    <x-slot:footerFiles>

        {{--
            Select2: el javascript, y antes que nada porque el script de la
            rejilla lo busca en window.iniciarSelect2 para engancharle el
            desplegable a cada fila que anade.

            Va antes a proposito. El @vite carga un modulo, que el navegador
            aplaza hasta despues de leer toda la pagina, mientras que el
            script de la rejilla es de los clasicos y se ejecuta al leerlo.
            Asi el modulo ya ha dejado window.iniciarSelect2 puesto cuando la
            rejilla construye su primera fila.
        --}}
        @vite(['resources/assets/js/select2/select2-init.js'])

        {{-- El javascript de la rejilla de lineas va aqui: necesita el
             formulario ya pintado para clonar su fila de ejemplo. --}}
        <script src="{{ asset('js/inventario/compras.js') }}"></script>

        {{--
            La tabla del selector va con los scripts del DataTable, y no
            dentro de la vista del modal: los scripts tienen que salir
            despues del modal en el html, y blade no tiene forma de
            decidir eso desde dentro del include.
        --}}
        {{ $selectorProveedor->html()->scripts() }}

        <script src="{{ asset('js/inventario/selector_proveedor.js') }}"></script>

    </x-slot>

</x-base-layout>
