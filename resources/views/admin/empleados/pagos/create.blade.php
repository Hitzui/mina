<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>
    <x-slot:headerFiles>
    </x-slot>
    <div class="page-header">
        <div class="page-title">
            <h3>{{ $title }}</h3>
        </div>
    </div>

    <div class="row layout-top-spacing">
        <div class="col-xl-10 col-lg-12 col-md-12 col-12 mx-auto">
            <div class="widget-content widget-content-area br-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="mb-1">
                            <i class="bi bi-cash-stack me-2"></i>
                            Nueva tarifa / condición de pago
                        </h5>
                        <p class="text-muted mb-0">
                            Registre la tarifa correspondiente al período del empleado.
                        </p>
                    </div>
                </div>

                <form action="{{ route('admin.empleados.pagos.store', $empleado) }}" method="POST">
                    @include('admin.empleados.pagos._form', ['submitText' => 'Guardar tarifa'])
                </form>
            </div>
        </div>
    </div>
    <x-slot:footerFiles>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const config = {
                    locale: 'es',
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    allowInput: false
                };

                flatpickr('#fecha_inicio', config);
                flatpickr('#fecha_fin', config);
            });
        </script>
    </x-slot>
</x-base-layout>
