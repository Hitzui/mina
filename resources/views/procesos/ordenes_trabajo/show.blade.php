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
                                <i class="bi bi-pencil-square me-1"></i> Editar
                            </a>
                            {{--
                                El boton va associado al formulario oculto con el
                                atributo HTML "form". La libreria de confirmacion
                                resuelve el borrado en este orden:

                                - si el elemento tiene href, arma un formulario y lo
                                  envia a esa url (patron de las filas del listado);
                                - si el elemento pertenece a un formulario, lo envia.

                                Con "form=" el boton pertenece al formulario aunque
                                este mas abajo en el DOM, asi que funciona el segundo
                                caso. Antes se usaba data-form-delete, que esa
                                libreria no soporta: sin href ni formulario la
                                libreria salia en silencio y el boton no hacia nada.
                            --}}
                            <button type="submit" form="formEliminarOrden" class="btn btn-danger">
                                <i class="bi bi-trash me-1"></i> Eliminar
                            </button>
                            <a href="{{ route('procesos.ordenes_trabajo.index') }}" class="btn btn-light">
                                <i class="bi bi-arrow-left me-1"></i> Volver
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
                    <h5 class="mb-4"><i class="bi bi-person me-2"></i>Cliente</h5>
                    <label class="text-muted small">Nombre</label>
                    <div class="fw-semibold">{{ $ordenTrabajo->cliente?->nombre ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-lg-6 col-md-6 mt-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-4"><i class="bi bi-file-text me-2"></i>Datos de la Orden</h5>
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
                        <h5 class="mb-3"><i class="bi bi-text-left me-2"></i>Descripción</h5>
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
                            <h5 class="mb-1"><i class="bi bi-gear me-2"></i>Procesos</h5>
                            <p class="text-muted mb-0">Procesos asociados a esta orden de trabajo.</p>
                        </div>
                        <a href="{{ route('procesos.ordenes_trabajo.procesos.create', $ordenTrabajo->id) }}" class="btn btn-primary">
                            <i class="bi bi-plus me-1"></i> Nuevo proceso
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
        procesos de arriba muestra cuanto cuesta cada uno.

        Lo mismo con los costos que si son de un proceso: se registran y se
        ven alli. Los de aqui son los que no son de ninguno.
    --}}

    <div class="row">
        <div class="col-12 mt-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">
                                <i class="bi bi-cash-stack me-2"></i>
                                Costos generales de la orden
                            </h5>
                            <p class="text-muted mb-0">
                                Los gastos de toda la orden, sin proceso concreto.
                            </p>
                        </div>

                        <button type="button" class="btn btn-primary" id="btnNuevoCostoOrden">
                            <i class="bi bi-plus-lg me-1"></i>
                            Registrar costo
                        </button>
                    </div>

                    <div class="alert alert-light border d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle fs-5"></i>
                        <div>
                            Aquí van alquiler, transporte, un insumo suelto: lo
                            que no pertenece a un proceso en concreto. Los
                            consumos de cada proceso (energía, agua, materia
                            prima) se registran en la pantalla de ese proceso,
                            y su costo se ve en la columna de la tabla de
                            procesos de arriba.
                        </div>
                    </div>

                    <div class="table-responsive">
                        {{ $costosDataTable->html()->table() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{--
        La confirmacion va en el formulario, no en el boton: asi la libreria
        lo pide antes de enviarlo y si se cancela no se envia nada.
    --}}
    <form id="formEliminarOrden"
          action="{{ route('procesos.ordenes_trabajo.destroy', $ordenTrabajo) }}"
          method="POST"
          class="d-none"
          data-confirm-delete
          data-confirm-title="¿Eliminar la orden de trabajo?"
          data-confirm-text="Esta acción no se puede deshacer."
          data-confirm-button="Sí, eliminar">
        @csrf
        @method('DELETE')
    </form>

    <x-slot:footerFiles>
        {{ $dataTable->html()->scripts() }}
        {{ $costosDataTable->html()->scripts() }}
        @vite(['resources/assets/js/select2/select2-init.js'])
        <script src="{{ asset('js/ordenes_trabajo/costos.js') }}"></script>
    </x-slot>

    @include('procesos.ordenes_trabajo.costos._modal_form')
    @include('procesos.ordenes_trabajo.costos._modal_show')

</x-base-layout>
