<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Nueva Categoría de Costo' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>


    <div class="row layout-top-spacing">

        <div class="col-xl-10 col-lg-10 col-md-12 col-sm-12 mx-auto">

            <div class="widget-content widget-content-area br-8">

                <div class="mb-4">
                    <h4 class="mb-1">
                        <i class="bi bi-tags me-2"></i>
                        Nueva Categoría de Costo
                    </h4>

                    <p class="text-muted mb-0">
                        Registre una nueva categoría para clasificar los costos.
                    </p>
                </div>

                @include('configuracion.categorias_costos._form')

            </div>

        </div>

    </div>


    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
