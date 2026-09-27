<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Editar Equipo' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12 layout-spacing">

            <div class="widget-content widget-content-area br-8">

                <div class="mb-4">
                    <h5 class="mb-1">
                        <i class="bi bi-pencil-square me-1"></i>
                        Editar Equipo
                    </h5>

                    <p class="text-muted mb-0">
                        Cambiar el valor o la vida útil cambia la depreciación
                        de los usos que se registren de aquí en adelante. Los
                        costos ya registrados en procesos anteriores conservan
                        su propia depreciación.
                    </p>
                </div>

                @include('admin.equipos._form')

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
