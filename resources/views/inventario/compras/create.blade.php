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

    <x-slot:footerFiles>

        {{-- El javascript de la rejilla de lineas va aqui: necesita el
             formulario ya pintado para clonar su fila de ejemplo. --}}
        <script src="{{ asset('js/inventario/compras.js') }}"></script>

    </x-slot>

</x-base-layout>
