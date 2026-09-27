<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Información del Equipo' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12 layout-spacing">

            <div class="widget-content widget-content-area br-8">

                {{-- Encabezado --}}
                <div class="d-flex justify-content-between align-items-center mb-4">

                    <div>
                        <h5 class="mb-1">
                            <i class="bi bi-gear-wide-connected me-1"></i>
                            {{ $equipo->nombre }}
                        </h5>

                        <p class="text-muted mb-0 font-monospace">
                            {{ $equipo->codigo }}
                        </p>
                    </div>

                    <div class="text-end">
                        <span class="badge {{ $equipo->estado ? 'bg-success' : 'bg-danger' }} fs-6">
                            {{ $equipo->estado ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                </div>

                {{-- Datos del equipo --}}
                <div class="row">

                    <div class="col-md-3 mb-4">
                        <label class="form-label text-muted">Fecha de adquisición</label>
                        <div class="fw-bold">
                            {{ $equipo->fecha_adquisicion?->format('d/m/Y') ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-3 mb-4">
                        <label class="form-label text-muted">Valor de adquisición</label>
                        <div class="fw-bold">
                            {{ number_format($equipo->valor_adquisicion, 2) }}
                        </div>
                    </div>

                    <div class="col-md-3 mb-4">
                        <label class="form-label text-muted">Valor residual</label>
                        <div class="fw-bold">
                            {{ number_format($equipo->valor_residual, 2) }}
                        </div>
                    </div>

                    <div class="col-md-3 mb-4">
                        <label class="form-label text-muted">Vida útil</label>
                        <div class="fw-bold">
                            {{ $equipo->vida_util_meses }}
                            <span class="text-muted fw-normal">meses</span>
                        </div>
                    </div>

                    <div class="col-12 mb-4">
                        <label class="form-label text-muted">Descripción</label>
                        <div>
                            {{ $equipo->descripcion ?: 'Sin descripción' }}
                        </div>
                    </div>
                </div>

                <hr>

                {{-- Cómo deprecia --}}
                <h6 class="mb-3">
                    <i class="bi bi-calculator me-1"></i>
                    Cómo deprecia
                </h6>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <label class="form-label text-muted">Depreciación por día</label>
                        <div class="form-control bg-light fw-semibold fs-5">
                            {{ number_format($equipo->depreciacionDiaria(), 4) }}
                        </div>
                        <div class="form-text">
                            (valor de adquisición − valor residual) ÷
                            (vida útil × {{ \App\Models\Equipo::DIAS_POR_MES }} días)
                        </div>
                    </div>

                    <div class="col-md-4 mb-4">
                        <label class="form-label text-muted">Falta por depreciar</label>
                        <div class="form-control bg-light fw-semibold">
                            {{ number_format($equipo->valorPorDepreciar(), 2) }}
                        </div>
                        <div class="form-text">
                            Lo que le queda de valor, descontando lo ya consumido.
                        </div>
                    </div>

                    <div class="col-md-4 mb-4">
                        <label class="form-label text-muted">Depreciación acumulada</label>
                        <div class="form-control bg-light fw-semibold">
                            {{ number_format($equipo->depreciacion_acumulada, 2) }}
                        </div>
                        <div class="form-text">
                            Suma de los usos registrados, sin recalcular.
                        </div>
                    </div>
                </div>

                <hr>

                {{-- Dónde se ha usado --}}
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">
                        <i class="bi bi-diagram-3 me-1"></i>
                        Procesos donde se usó
                    </h6>

                    <span class="badge bg-secondary">
                        {{ $asignaciones->count() }}
                    </span>
                </div>

                @if($asignaciones->isEmpty())
                    <div class="alert alert-light border mb-4">
                        <i class="bi bi-info-circle me-1"></i>
                        Este equipo todavía no está asignado a ningún proceso,
                        así que no ha cargado costo por depreciación.
                    </div>
                @else
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Orden</th>
                                    <th>Proceso</th>
                                    <th>Inicio</th>
                                    <th>Fin</th>
                                    <th class="text-center">Días</th>
                                    <th class="text-end">Depreciación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($asignaciones as $uso)
                                    <tr>
                                        <td>
                                            <a href="{{ route('procesos.ordenes_trabajo.show', $uso->procesoOrden?->orden_trabajo_id) }}">
                                                {{ $uso->procesoOrden?->orden_trabajo?->codigo ?? '—' }}
                                            </a>
                                        </td>
                                        <td>
                                            <a href="{{ route('procesos.ordenes_trabajo.procesos.show', [$uso->procesoOrden?->orden_trabajo_id, $uso->procesoOrden?->id]) }}">
                                                {{ $uso->procesoOrden?->nombre_completo ?? '—' }}
                                            </a>
                                        </td>
                                        <td>
                                            {{ $uso->fecha_inicio?->format('d/m/Y H:i') ?? '—' }}
                                        </td>
                                        <td>
                                            @if($uso->fecha_fin)
                                                {{ $uso->fecha_fin->format('d/m/Y H:i') }}
                                            @else
                                                <span class="badge bg-warning text-dark">Sigue asignado</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            {{ rtrim(rtrim(number_format($uso->diasDeUso(), 2), '0'), '.') }}
                                        </td>
                                        <td class="text-end fw-semibold">
                                            {{ number_format($uso->depreciacion_total, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="5" class="text-end">
                                        Total depreciado
                                    </th>
                                    <th class="text-end">
                                        {{ number_format($asignaciones->sum('depreciacion_total'), 2) }}
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif

                {{-- Acciones --}}
                <div class="mt-4 d-flex justify-content-end gap-2">

                    <a
                        href="{{ route('admin.equipos.index') }}"
                        class="btn btn-light">
                        <i class="bi bi-arrow-return-left me-1"></i> Regresar
                    </a>

                    <a
                        href="{{ route('admin.equipos.edit', $equipo) }}"
                        class="btn btn-warning">
                        <i class="bi bi-pencil-square me-1"></i> Editar
                    </a>

                    {{--
                        El borrado se avisa antes: un equipo con historial no
                        se puede eliminar, y el mensaje de la confirmación
                        lo dice para que la sorpresa no llegue después.
                    --}}
                    <form
                        action="{{ route('admin.equipos.destroy', $equipo) }}"
                        method="POST"
                        class="d-inline"
                        data-confirm-delete="true"
                        data-confirm-title="¿Eliminar Equipo?"
                        data-confirm-text="¿Desea eliminar el equipo del sistema? Si el equipo ya se usó en algún proceso no se podrá eliminar, porque los costos registrados dependen de él."
                        data-confirm-button="Sí, eliminar">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i> Eliminar
                        </button>
                    </form>

                </div>

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
