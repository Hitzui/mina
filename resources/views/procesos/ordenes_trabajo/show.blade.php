<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title ?? 'Información de Orden de Trabajo' }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">
        <div class="col-xl-12 col-lg-12 col-sm-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <h4 class="mb-0">{{ $ordenTrabajo->codigo }}</h4>
                                @switch($ordenTrabajo->estado)
                                    @case(1)
                                        <span class="badge bg-primary">Pendiente</span>
                                        @break
                                    @case(2)
                                        <span class="badge bg-warning">En proceso</span>
                                        @break
                                    @case(3)
                                        <span class="badge bg-success">Finalizada</span>
                                        @break
                                    @case(4)
                                        <span class="badge bg-danger">Cancelada</span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary">Desconocido</span>
                                @endswitch
                            </div>
                            <p class="text-muted mb-0">Información de la Orden de Trabajo</p>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('procesos.ordenes_trabajo.edit', $ordenTrabajo) }}" class="btn btn-primary">
                                <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                            </a>
                            <button type="button" class="btn btn-danger" data-confirm-delete="true" data-form-delete="#formEliminarOrden">
                                <i class="fa-solid fa-trash me-1"></i> Eliminar
                            </button>
                            <a href="{{ route('procesos.ordenes_trabajo.index') }}" class="btn btn-light">
                                <i class="fa-solid fa-arrow-left me-1"></i> Volver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6 col-lg-6 col-md-6 mt-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-4"><i class="fa-solid fa-user me-2"></i>Cliente</h5>
                    <label class="text-muted small">Nombre</label>
                    <div class="fw-semibold">{{ $ordenTrabajo->cliente?->nombre ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-lg-6 col-md-6 mt-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-4"><i class="fa-solid fa-file-lines me-2"></i>Datos de la Orden</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small">Fecha</label>
                            <div class="fw-semibold">{{ $ordenTrabajo->fecha?->format('d/m/Y') ?? '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Peso del mineral</label>
                            <div class="fw-semibold">
                                {{ number_format($ordenTrabajo->peso_mineral, 4, '.', ',') }}
                                {{ ucfirst($ordenTrabajo->unidad_peso) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($ordenTrabajo->descripcion)
        <div class="row">
            <div class="col-12 mt-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3"><i class="fa-solid fa-align-left me-2"></i>Descripción</h5>
                        <div class="text-muted">{!! nl2br(e($ordenTrabajo->descripcion)) !!}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-12 mt-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="mb-1"><i class="fa-solid fa-gears me-2"></i>Procesos</h5>
                            <p class="text-muted mb-0">Procesos asociados a esta orden de trabajo.</p>
                        </div>
                        <a href="{{ route('procesos.ordenes_trabajo.procesos.create', $ordenTrabajo->id) }}" class="btn btn-primary">
                            <i class="fa-solid fa-plus me-1"></i> Nuevo proceso
                        </a>
                    </div>

                    <div class="table-responsive">
                        {{ $dataTable->html()->table() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{--
        Los trabajos de los empleados ya no se listan aqui: cuelgan de
        cada proceso, asi que se ven entrando al proceso. La tabla de
        procesos de arriba muestra cuanto cuesta cada uno en mano de obra.
    --}}

    <form id="formEliminarOrden" action="{{ route('procesos.ordenes_trabajo.destroy', $ordenTrabajo) }}" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <x-slot:footerFiles>
        {{ $dataTable->html()->scripts() }}
    </x-slot>

</x-base-layout>
