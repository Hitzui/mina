<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Nuevo Tipo de Pago' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-10 col-lg-10 col-md-12 col-sm-12 mx-auto">

            <div class="widget-content widget-content-area br-8">

                <div class="mb-4">

                    <h4 class="mb-1">
                        <i class="bi bi-cash-stack me-2"></i>
                        Nuevo Tipo de Pago
                    </h4>

                    <p class="text-muted mb-0">
                        Registre un nuevo tipo de pago para empleados.
                    </p>

                </div>

                @include(
                    'configuracion.tipos_pago_empleado._form'
                )

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
