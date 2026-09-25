<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Ingresar Orden de Trabajo' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    {{-- Contenido --}}
    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12 layout-spacing">

            <div class="card">

                <div class="card-body">

                    <div class="widget-content widget-content-area">

                        @include('procesos.ordenes_trabajo._form')

                    </div>

                </div>

            </div>

        </div>

    </div>

    <x-slot:footerFiles>

        {{-- Selector de clientes --}}
        <script src="{{ asset('js/clientes/modal.js') }}"></script>

        {{-- JavaScript específico de crear OT --}}
        <script>
            $(document).on('cliente:seleccionado', function (event, cliente) {

                $('#cliente_id').val(cliente.id);
                $('#cliente_nombre').val(cliente.nombre);

            });
        </script>

        {{-- Flatpickr --}}
        <script>
            document.addEventListener('DOMContentLoaded', function () {

                if (typeof flatpickr !== 'undefined') {

                    flatpickr('#fecha', {
                        dateFormat: 'Y-m-d',
                        altInput: true,
                        altFormat: 'd/m/Y',
                        locale: 'es',
                        allowInput: true
                    });

                }

            });
        </script>

    </x-slot>

</x-base-layout>
