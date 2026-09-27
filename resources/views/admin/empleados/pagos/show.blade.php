<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">
        <div class="col-xl-10 col-lg-12 col-md-12 col-12 mx-auto">
            <div class="widget-content widget-content-area br-8">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h5 class="mb-1">
                            <i class="bi bi-cash-stack me-2"></i>
                            Detalle de tarifa
                        </h5>
                        <p class="text-muted mb-0">
                            {{ $empleado->nombre }} · {{ $empleado->codigo }}
                        </p>
                    </div>

                    <span class="badge {{ $empleadoPago->estado ? 'badge-light-success' : 'badge-light-danger' }}">
                        {{ $empleadoPago->estado ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Tipo de pago</div>
                        <div class="fw-bold">{{ $empleadoPago->tipo_pago?->nombre ?? '—' }}</div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-muted small">Tarifa</div>
                        <div class="fw-bold">{{ number_format($empleadoPago->tarifa, 2) }}</div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-muted small">Moneda</div>
                        <div class="fw-bold">{{ $empleadoPago->moneda?->codigo ?? '—' }}</div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small">Fecha de inicio</div>
                        <div class="fw-bold">{{ $empleadoPago->fecha_inicio?->format('d/m/Y') ?? '—' }}</div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small">Fecha de finalización</div>
                        <div class="fw-bold">{{ $empleadoPago->fecha_fin?->format('d/m/Y') ?? 'Vigente' }}</div>
                    </div>

                    <div class="col-12">
                        <div class="text-muted small">Observaciones</div>
                        <div>{{ $empleadoPago->observaciones ?: 'Sin observaciones.' }}</div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Creado</div>
                        <div>{{ $empleadoPago->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small">Última actualización</div>
                        <div>{{ $empleadoPago->updated_at?->format('d/m/Y H:i') ?? '—' }}</div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('admin.empleados.show', $empleado) }}" class="btn btn-light">
                        <i class="bi bi-arrow-left me-1"></i> Volver al empleado
                    </a>

                    <a href="{{ route('admin.empleados.pagos.edit', [$empleado, $empleadoPago]) }}" class="btn btn-warning">
                        <i class="bi bi-pencil me-1"></i> Editar
                    </a>

                    <a href="{{ route('admin.empleados.pagos.destroy', [$empleado, $empleadoPago]) }}"
                       class="btn btn-danger"
                       data-confirm-delete
                       data-confirm-title="¿Eliminar tarifa?"
                       data-confirm-text="¿Desea eliminar esta tarifa del historial del empleado? Esta acción no se puede revertir."
                       data-confirm-button="Sí, eliminar">
                        <i class="bi bi-trash me-1"></i> Eliminar
                    </a>
                </div>
            </div>
        </div>
    </div>
    <x-slot:footerFiles>
    </x-slot:footerFiles>
</x-base-layout>
