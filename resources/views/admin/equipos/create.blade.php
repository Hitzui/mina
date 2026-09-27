<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Nuevo Equipo' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12 layout-spacing">

            <div class="widget-content widget-content-area br-8">

                <div class="mb-4">
                    <h5 class="mb-1">
                        <i class="bi bi-gear-wide-connected me-1"></i>
                        Nuevo Equipo
                    </h5>

                    <p class="text-muted mb-0">
                        Registre un equipo del taller. Del valor de adquisición,
                        el valor residual y la vida útil se calcula lo que se
                        deprecia por cada día de uso.
                    </p>
                </div>

                @include('admin.equipos._form')

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
