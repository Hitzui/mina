<div class="card mt-4 mb-4" id="seguimiento-ot" data-tracking-summary data-tracking-url="{{ route('procesos.ordenes_trabajo.seguimiento.show', $ordenTrabajo) }}?summary=1"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div><h5 class="mb-1">Avance de la OT y tiempo de reparación</h5><p class="text-muted small mb-0">Cada proceso vigente tiene el mismo peso; los cancelados quedan fuera del porcentaje.</p></div>
        <a class="btn btn-outline-primary" href="{{ route('procesos.ordenes_trabajo.seguimiento.show', $ordenTrabajo) }}">Reparaciones y plazos</a>
    </div>
    <div class="d-flex justify-content-between mb-2"><strong>{{ $seguimiento['completed_processes'] }} de {{ $seguimiento['total_processes'] }} procesos finalizados</strong><strong>{{ $seguimiento['percent'] === null ? 'Sin procesos vigentes' : $seguimiento['percent'].'%' }}</strong></div>
    <div class="progress mb-2" style="height:18px" role="progressbar" aria-label="Avance por procesos finalizados" aria-valuenow="{{ $seguimiento['percent'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width:{{ $seguimiento['percent'] ?? 0 }}%"></div></div>
    @if($seguimiento['cancelled_processes'])<p class="small text-muted">{{ $seguimiento['cancelled_processes'] }} proceso(s) cancelado(s).</p>@endif
    @if($seguimiento['timeline'])
        <h6 class="mt-4">Línea de tiempo de las asignaciones</h6>
        @include('procesos.seguimiento._barra', ['barra' => $seguimiento['timeline'], 'etiqueta' => 'Tiempo de las asignaciones de la OT'])
        <div class="d-flex justify-content-between flex-wrap small text-muted mt-2"><span>{{ str_replace('T', ' ', $seguimiento['timeline']['start']) }}</span><span>{{ str_replace('T', ' ', $seguimiento['timeline']['end']) }}</span></div>
        <p class="small mt-2 mb-0"><span class="text-info">■ Transcurrido</span> · <span class="text-danger">■ Reparación</span> · <span class="text-muted">□ Por transcurrir</span>. {{ number_format($seguimiento['timeline']['hours_in_repair'], 2) }} horas calendario con al menos un equipo en reparación. Los intervalos simultáneos se cuentan una vez.</p>
        <p class="text-muted small mt-1 mb-0">Tiempo asignado no equivale a horas trabajadas. El horómetro sigue determinando el uso real; una reparación no amplía el plazo ni crea costos automáticamente.</p>
    @else <p class="text-muted small mt-3 mb-0">Asigne equipos a los procesos para ver su línea de tiempo.</p> @endif
    <p class="small text-muted mt-2 mb-0" data-tracking-status>Calculado: {{ $seguimiento['generated_at'] }}. Las barras se actualizan cada 30 segundos mientras esta página está visible.</p>
</div></div>
@once
    <script defer src="{{ asset('js/seguimiento-orden.js') }}"></script>
@endonce
