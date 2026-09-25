<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Editar Proceso' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
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
                                <i class="fa-solid fa-gears me-2"></i>
                                Editar Proceso
                            </h4>

                            <p class="text-muted mb-0">
                                Actualizar la información del proceso.
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
                                <i class="fa-solid fa-arrow-left me-1"></i>
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
                        <i class="fa-solid fa-file-lines me-2"></i>
                        Orden de Trabajo
                    </h5>

                    <div class="row g-3">

                        <div class="col-md-4">

                            <label class="text-muted small">
                                Código de OT
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
                                Proceso
                            </label>

                            <div class="fw-semibold">
                                {{ $procesoOrden->codigo }}
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
                        <i class="fa-solid fa-pen-to-square me-2"></i>
                        Datos del Proceso
                    </h5>

                    @include(
                        'procesos.procesos_orden._form',
                        [
                            'modo' => 'edit',
                            'ordenTrabajo' => $ordenTrabajo,
                            'procesoOrden' => $procesoOrden,
                            'etapas' => $etapas,
                        ]
                    )

                </div>

            </div>

        </div>

    </div>


    <x-slot:footerFiles>

        <script src="{{ asset('js/procesos_orden/formulario.js') }}"></script>

    </x-slot>

</x-base-layout>
