<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title ?? 'Información del Empleado' }}</x-slot:pageTitle>
    <x-slot name="headerFiles">
        @vite(['resources/scss/light/plugins/select2/custom-select2.scss'])
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <div class="row layout-top-spacing">
        <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12">
            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-1">{{ $empleado->nombre }}</h4>
                        <span class="text-muted">Código: {{ $empleado->codigo }}</span>
                    </div>
                    <div>
                        @if($empleado->estado)
                            <span class="badge bg-success">Activo</span>
                        @else
                            <span class="badge bg-danger">Inactivo</span>
                        @endif
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="text-muted mb-1">Código</label>
                        <div class="fw-semibold">{{ $empleado->codigo }}</div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="text-muted mb-1">Nombre</label>
                        <div class="fw-semibold">{{ $empleado->nombre }}</div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="text-muted mb-1">Teléfono</label>
                        <div>{{ $empleado->telefono ?: 'No registrado' }}</div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="text-muted mb-1">Modalidad de empleado</label>
                        <div>{{ $empleado->tipo_empleado?->nombre ?? 'No registrada' }}</div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="text-muted mb-1">Fecha de ingreso</label>
                        <div>
                            @if($empleado->fecha_ingreso)
                                {{ $empleado->fecha_ingreso->format('d/m/Y') }}
                            @else
                                No registrada
                            @endif
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="text-muted mb-1">Estado</label>
                        <div>
                            @if($empleado->estado)
                                <span class="badge bg-success">Activo</span>
                            @else
                                <span class="badge bg-danger">Inactivo</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-12 mb-4">
                        <label class="text-muted mb-1">Observaciones</label>
                        <div class="border rounded p-3">
                            @if($empleado->observaciones)
                                {!! nl2br(e($empleado->observaciones)) !!}
                            @else
                                <span class="text-muted">No hay observaciones registradas.</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="border-top pt-4 mt-2">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="mb-1"><i class="bi bi-cash-stack me-2"></i>Pagos y tarifas</h5>
                            <p class="text-muted mb-0">Historial de tarifas y condiciones de pago del empleado.</p>
                        </div>
                        <button type="button" class="btn btn-outline-info" id="btnNuevaTarifa"
                                data-url="{{ route('admin.empleados.pagos.create', $empleado) }}">
                            <i class="bi bi-plus me-1"></i> Nueva tarifa
                        </button>
                    </div>

                    <div class="table-responsive">
                        {{ $empleadosPagosDataTable->html()->table() }}
                    </div>
                </div>

                <div class="border-top pt-3 mt-4">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">Registrado:</small>
                            <div>{{ $empleado->created_at?->format('d/m/Y H:i') ?? 'N/A' }}</div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Última actualización:</small>
                            <div>{{ $empleado->updated_at?->format('d/m/Y H:i') ?? 'N/A' }}</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('admin.empleados.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-return-left"></i> Regresar
                    </a>

                    <a href="{{ route('admin.empleados.edit', $empleado) }}" class="btn btn-primary">
                        <i class="bi bi-pencil-square"></i> Editar
                    </a>

                    <form action="{{ route('admin.empleados.destroy', $empleado) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="btn btn-danger"
                            data-confirm-delete
                            data-confirm-title="¿Eliminar el empleado?"
                            data-confirm-text="Se eliminará de la lista de empleados. Sus tarifas y sus trabajos se conservan porque el historial de la orden los necesita; lo que se pierde es la posibilidad de registrarle trabajo nuevo."
                            data-confirm-button="Sí, eliminar">
                            <i class="bi bi-trash"></i> Eliminar
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    @include('admin.empleados.pagos._modal_show')
    @include('admin.empleados.pagos._modal_form')

    <x-slot name="footerFiles">
        @vite(['resources/assets/js/select2/select2-init.js'])
        {!! $empleadosPagosDataTable->html()->scripts() !!}
        <script src="{{ asset('js/empleados/pagos/modal.js') }}"></script>
        <script src="{{ asset('js/empleados/pagos/form.js') }}"></script>
    </x-slot>
</x-base-layout>
