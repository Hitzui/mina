<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Información del Proceso' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
        @vite(['resources/scss/light/plugins/select2/custom-select2.scss'])
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12">

            <div class="widget-content widget-content-area br-8">

                {{-- Datos del proceso y lo que se le paga --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label text-muted">Código</label>
                        <div class="form-control bg-light font-monospace">
                            {{ $procesoOrden->codigo ?: '—' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Etapa</label>
                        <div class="form-control bg-light">
                            {{ $procesoOrden->etapa?->nombre ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Estado</label>
                        <div class="pt-2">
                            @if($procesoOrden->estado)
                                <span class="badge bg-success fs-6">
                                    <i class="bi bi-check-circle me-1"></i> Activo
                                </span>
                            @else
                                <span class="badge bg-secondary fs-6">
                                    <i class="bi bi-x-circle me-1"></i> Inactivo
                                </span>
                            @endif
                        </div>
                    </div>

                    {{--
                        El costo se calcula al momento sumando los trabajos
                        del proceso, por eso no hay que mantenerlo sincronizado.
                    --}}
                    <div class="col-md-6">
                        <label class="form-label text-muted">Costo de mano de obra del proceso</label>
                        <div class="form-control bg-light fw-semibold fs-5">
                            {{ number_format($procesoOrden->costo_empleados, 2) }}
                        </div>
                        <div class="form-text">
                            Suma de los
                            {{ $cantidadTrabajos }}
                            trabajo(s) registrados en este proceso.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Fechas</label>
                        <div class="form-control bg-light">
                            {{ $procesoOrden->fecha_inicio?->format('d/m/Y') ?? '—' }}
                            &nbsp;a&nbsp;
                            {{ $procesoOrden->fecha_fin?->format('d/m/Y') ?? 'sin fecha de fin' }}
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2"></i>
                        Trabajos de los empleados
                    </h5>

                    <button type="button" class="btn btn-primary" id="btnNuevoTrabajoEmpleado">
                        <i class="bi bi-plus-lg me-1"></i>
                        Nuevo trabajo
                    </button>
                </div>

                {!! $trabajosDataTable->html()->table(['class' => 'table table-hover'], true) !!}

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
        {{ $trabajosDataTable->html()->scripts() }}
        {{ $empleadosSelectorDataTable->html()->scripts() }}
        @vite(['resources/assets/js/select2/select2-init.js'])
        <script src="{{ asset('js/ordenes_trabajo/trabajos_empleados.js') }}"></script>
    </x-slot>

    @include('procesos.ordenes_trabajo.trabajos_empleados._modal_form')
    @include('admin.empleados._modal_empleado')
    @include('procesos.ordenes_trabajo.trabajos_empleados._modal_show')

</x-base-layout>
