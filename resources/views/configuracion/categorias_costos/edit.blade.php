<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Editar Categoría de Costo' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>


    <div class="row layout-top-spacing">

        <div class="col-xl-10 col-lg-10 col-md-12 col-sm-12 mx-auto">

            <div class="widget-content widget-content-area br-8">

                <div class="mb-4">
                    <h4 class="mb-1">
                        <i class="bi bi-pencil-square me-2"></i>
                        Editar Categoría de Costo
                    </h4>

                    <p class="text-muted mb-0">
                        Modifique la información de la categoría de costo.
                    </p>
                </div>

                @include('configuracion.categorias_costos._form')

            </div>

        </div>

    </div>


    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
