<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Ingresar Etapa' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12 layout-spacing">

            <div class="widget-content widget-content-area br-8">

                <div class="mb-4">
                    <h5 class="mb-1">
                        Ingresar Etapa
                    </h5>

                    <p class="text-muted mb-0">
                        Registre una nueva etapa del proceso.
                    </p>
                </div>

                @include('admin.etapas._form')

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
