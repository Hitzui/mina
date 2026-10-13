<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles></x-slot>

    <div class="row layout-top-spacing">
        <div class="col-12">
            <div class="card"><div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h4 class="mb-0">{{ $orden->codigo }}</h4>
                        {!! $orden->estadoEtiqueta() !!}
                    </div>
                    <p class="text-muted mb-0">Resumen operativo y financiero · {{ $orden->cliente?->nombre ?? 'Sin cliente' }}</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @if($puedeExportar)
                        <a href="{{ route('procesos.ordenes_trabajo.resumen.xlsx', $orden) }}" class="btn btn-outline-success">
                            <i class="bi bi-file-earmark-excel me-1"></i> Descargar Excel
                        </a>
                        <a href="{{ route('procesos.ordenes_trabajo.resumen.pdf', $orden) }}" class="btn btn-outline-danger">
                            <i class="bi bi-file-earmark-pdf me-1"></i> Descargar reporte PDF
                        </a>
                    @endif
                    <a href="{{ route('procesos.ordenes_trabajo.show', $orden) }}" class="btn btn-light">Volver a la orden</a>
                </div>
            </div></div>
        </div>
    </div>

    @if($costos['directos_sin_equivalente'] > 0 || $costos['generales_sin_equivalente'] > 0 || $ingresos['sin_equivalente'] > 0 || $costos['generales_pendientes_nio'] > 0)
        <div class="row mt-3"><div class="col-12">
            <div class="alert alert-warning mb-0">
                <h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>El resumen tiene datos incompletos</h6>
                <ul class="mb-0">
                    @if($costos['directos_sin_equivalente'] > 0)<li>{{ $costos['directos_sin_equivalente'] }} costo(s) directo(s) de proceso no tienen equivalente en NIO.</li>@endif
                    @if($costos['generales_sin_equivalente'] > 0)<li>{{ $costos['generales_sin_equivalente'] }} costo(s) general(es) no tienen equivalente en NIO.</li>@endif
                    @if($ingresos['sin_equivalente'] > 0)<li>{{ $ingresos['sin_equivalente'] }} ingreso(s) no tienen equivalente en NIO.</li>@endif
                    @if($costos['generales_pendientes_nio'] > 0)
                        <li>{{ number_format($costos['generales_pendientes_nio'], 2) }} NIO de gastos generales no se distribuyeron entre procesos. {{ $costos['motivo_pendiente'] }}</li>
                    @endif
                </ul>
                <div class="small mt-2">Los importes conocidos se muestran como referencia; no representan un total completo mientras existan pendientes.</div>
            </div>
        </div></div>
    @endif

    <div class="row g-3 mt-1">
        <div class="col-xl-3 col-md-6"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Ingresos registrados</div><div class="fs-4 fw-bold">NIO {{ number_format($ingresos['total_nio'], 2) }}</div>
            <div class="small text-muted">{{ $ingresos['cantidad'] }} registro(s) facturados</div>
        </div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Costos directos de procesos</div><div class="fs-4 fw-bold">NIO {{ number_format($costos['directos_nio'], 2) }}</div>
            <div class="small text-muted">Mano de obra, equipos, materiales y costos registrados</div>
        </div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Costos generales</div><div class="fs-4 fw-bold">NIO {{ number_format($costos['generales_nio'], 2) }}</div>
            <div class="small text-muted">NIO {{ number_format($costos['generales_distribuidos_nio'], 2) }} distribuidos a procesos</div>
        </div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Costos conocidos de la orden</div><div class="fs-4 fw-bold">NIO {{ number_format($costos['total_conocido_nio'], 2) }}</div>
            <div class="small text-muted">{{ $procesos->count() }} proceso(s)</div>
        </div></div></div>
    </div>

    <div class="row mt-4"><div class="col-12"><div class="card"><div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
            <div><h5 class="mb-1">Seguimiento por proceso</h5><p class="text-muted mb-0">Costos directos más la asignación informativa de gastos generales.</p></div>
            <div class="text-muted small align-self-center">No se crean ni duplican movimientos de costo por distribución.</div>
        </div>
        <div class="table-responsive"><table class="table table-hover table-bordered align-middle">
            <thead><tr><th>Proceso</th><th>Etapa</th><th>Estado</th><th>Fechas</th><th>Equipos asignados</th><th class="text-end">Costo directo NIO</th><th class="text-end">General asignado NIO</th><th class="text-end">Costo con general NIO</th></tr></thead>
            <tbody>
            @forelse($procesos as $item)
                @php($proceso = $item['proceso'])
                <tr>
                    <td><a href="{{ route('procesos.ordenes_trabajo.procesos.show', [$orden, $proceso]) }}">{{ $proceso->codigo }}</a></td>
                    <td>{{ $proceso->etapa?->nombre ?? '—' }}</td>
                    <td>{!! $proceso->estadoEtiqueta() !!}</td>
                    <td>{{ $proceso->fecha_inicio?->format('d/m/Y') ?? 'Sin inicio' }} – {{ $proceso->fecha_fin?->format('d/m/Y') ?? 'En curso' }}</td>
                    <td>
                        @forelse($item['equipos'] as $uso)
                            <div>{{ $uso->equipo?->codigo }} · {{ $uso->equipo?->nombre }}</div>
                        @empty
                            <span class="text-muted">Sin equipo asignado</span>
                        @endforelse
                    </td>
                    <td class="text-end">{{ number_format($item['costo_directo_nio'], 2) }}</td>
                    <td class="text-end">{{ number_format($item['gasto_general_asignado_nio'], 2) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($item['costo_total_nio'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">La orden aún no tiene procesos registrados.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div></div></div>
    <x-slot:footerFiles></x-slot>
</x-base-layout>
