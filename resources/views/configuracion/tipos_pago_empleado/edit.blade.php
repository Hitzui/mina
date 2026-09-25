<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Editar Tipo de Pago' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        @vite(['resources/scss/light/plugins/select2/custom-select2.scss'])
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-10 col-lg-10 col-md-12 col-sm-12 mx-auto">

            <div class="widget-content widget-content-area br-8">

                <div class="mb-4">

                    <h4 class="mb-1">
                        <i class="bi bi-pencil-square me-2"></i>
                        Editar Tipo de Pago
                    </h4>

                    <p class="text-muted mb-0">
                        Modifique la información del tipo de pago.
                    </p>

                </div>

                @include(
                    'configuracion.tipos_pago_empleado._form'
                )

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
        @vite(['resources/assets/js/select2/select2-init.js'])
    </x-slot>

</x-base-layout>
