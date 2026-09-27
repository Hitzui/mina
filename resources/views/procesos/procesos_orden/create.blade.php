<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Nuevo Proceso' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        @vite(['resources/scss/light/plugins/select2/custom-select2.scss'])
    </x-slot>


    {{-- ============================================================
         ENCABEZADO
    ============================================================= --}}

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12">

            <div class="card">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <h4 class="mb-1">
                                <i class="bi bi-gear me-2"></i>
                                Nuevo Proceso
                            </h4>

                            <p class="text-muted mb-0">
                                Registrar un nuevo proceso para la orden de trabajo.
                            </p>

                        </div>

                        <div>

                            <a
                                href="{{ route(
                                    'procesos.ordenes_trabajo.show',
                                    $ordenTrabajo
                                ) }}"
                                class="btn btn-light"
                            >
                                <i class="bi bi-arrow-left me-1"></i>
                                Volver
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- ============================================================
         INFORMACIÓN DE LA ORDEN
    ============================================================= --}}

    <div class="row">

        <div class="col-12 mt-4">

            <div class="card">

                <div class="card-body">

                    <h5 class="mb-4">
                        <i class="bi bi-file-text me-2"></i>
                        Orden de Trabajo
                    </h5>

                    <div class="row g-3">

                        <div class="col-md-4">

                            <label class="text-muted small">
                                Código
                            </label>

                            <div class="fw-semibold">
                                {{ $ordenTrabajo->codigo }}
                            </div>

                        </div>

                        <div class="col-md-4">

                            <label class="text-muted small">
                                Cliente
                            </label>

                            <div class="fw-semibold">
                                {{ $ordenTrabajo->cliente?->nombre ?? '-' }}
                            </div>

                        </div>

                        <div class="col-md-4">

                            <label class="text-muted small">
                                Peso del mineral
                            </label>

                            <div class="fw-semibold">

                                {{ number_format(
                                    $ordenTrabajo->peso_mineral,
                                    4,
                                    '.',
                                    ','
                                ) }}

                                {{ ucfirst($ordenTrabajo->unidad_peso) }}

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- ============================================================
         FORMULARIO
    ============================================================= --}}

    <div class="row">

        <div class="col-12 mt-4">

            <div class="card">

                <div class="card-body">

                    <h5 class="mb-4">
                        <i class="bi bi-gear me-2"></i>
                        Datos del Proceso
                    </h5>

                    @include(
                        'procesos.procesos_orden._form',
                        [
                            'modo' => 'create',
                            'ordenTrabajo' => $ordenTrabajo,
                            'procesoOrden' => $procesoOrden ?? null,
                            'etapas' => $etapas,
                        ]
                    )

                </div>

            </div>

        </div>

    </div>


    <x-slot:footerFiles>
        @vite(['resources/assets/js/select2/select2-init.js'])

        <script src="{{ asset('js/procesos_orden/formulario.js') }}"></script>

    </x-slot>

</x-base-layout>
