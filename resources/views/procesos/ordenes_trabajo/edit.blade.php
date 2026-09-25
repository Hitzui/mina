<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Editar Orden de Trabajo' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12">

            <div class="card">

                <div class="card-body">

                    <div class="mb-4">
                        <h4 class="mb-1">
                            Editar Orden de Trabajo
                        </h4>

                        <p class="text-muted mb-0">
                            Modifique la información de la orden de trabajo.
                        </p>
                    </div>

                    @include('procesos.ordenes_trabajo._form')

                </div>

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
        <script src="{{ asset('js/clientes/modal.js') }}"></script>
        <script src="{{ asset('js/ordenes/formulario.js') }}"></script>
    </x-slot>

</x-base-layout>
