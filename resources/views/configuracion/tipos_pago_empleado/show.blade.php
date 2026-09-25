<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Tipo de Pago de Empleado' }}
    </x-slot>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-10 col-lg-10 col-md-12 col-sm-12 mx-auto">

            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-4">

                    <div>

                        <h4 class="mb-1">
                            <i class="bi bi-cash-stack me-2"></i>
                            Tipo de Pago de Empleado
                        </h4>

                        <p class="text-muted mb-0">
                            Información del tipo de pago.
                        </p>

                    </div>

                    <div class="d-flex gap-2">

                        <a
                            href="{{ route(
                                'configuracion.tipos_pago_empleado.index'
                            ) }}"
                            class="btn btn-outline-secondary"
                        >
                            <i class="bi bi-arrow-left me-1"></i>
                            Volver
                        </a>

                        <a
                            href="{{ route(
                                'configuracion.tipos_pago_empleado.edit',
                                $tipoPagoEmpleado
                            ) }}"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-pencil-square me-1"></i>
                            Editar
                        </a>

                    </div>

                </div>

                <div class="row">

                    {{-- Nombre --}}
                    <div class="col-md-8 mb-4">

                        <label class="form-label text-muted">
                            Nombre
                        </label>

                        <div class="form-control bg-light">
                            {{ $tipoPagoEmpleado->nombre }}
                        </div>

                    </div>

                    {{-- Estado --}}
                    <div class="col-md-4 mb-4">

                        <label class="form-label text-muted d-block">
                            Estado
                        </label>

                        @if($tipoPagoEmpleado->estado)

                            <span class="badge bg-success fs-6">
                                <i class="bi bi-check-circle me-1"></i>
                                Activo
                            </span>

                        @else

                            <span class="badge bg-secondary fs-6">
                                <i class="bi bi-x-circle me-1"></i>
                                Inactivo
                            </span>

                        @endif

                    </div>

                    {{-- Descripción --}}
                    <div class="col-12 mb-4">

                        <label class="form-label text-muted">
                            Descripción
                        </label>

                        <div
                            class="form-control bg-light"
                            style="min-height: 100px;"
                        >
                            {{ $tipoPagoEmpleado->descripcion ?: 'Sin descripción' }}
                        </div>

                    </div>

                </div>

                <div class="border-top pt-4 mt-2">

                    <h6 class="mb-3">
                        <i class="bi bi-clock-history me-2"></i>
                        Información de registro
                    </h6>

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <div class="text-muted small">
                                Fecha de creación
                            </div>

                            <div class="fw-semibold">
                                {{
                                    $tipoPagoEmpleado->created_at
                                        ?->format('d/m/Y H:i')
                                    ?? '-'
                                }}
                            </div>

                        </div>

                        <div class="col-md-6 mb-3">

                            <div class="text-muted small">
                                Última actualización
                            </div>

                            <div class="fw-semibold">
                                {{
                                    $tipoPagoEmpleado->updated_at
                                        ?->format('d/m/Y H:i')
                                    ?? '-'
                                }}
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
