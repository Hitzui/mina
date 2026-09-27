<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Nuevo Material' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-8 col-lg-10 col-sm-12 layout-spacing">

            <div class="widget-content widget-content-area br-8">

                <div class="mb-4">
                    <h5 class="mb-1">
                        <i class="bi bi-box-seam me-1"></i>
                        Nuevo Material
                    </h5>

                    <p class="text-muted mb-0">
                        Un material del almacén: cemento, un químico, un reactivo.
                        El material por sí solo no dice cuánto hay ni a cuánto
                        cuesta; eso aparece cuando se registre una entrada.
                    </p>
                </div>

                <form method="POST" action="{{ route('inventario.productos.store') }}">
                    @csrf

                    @include('inventario.productos._form')
                </form>

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
