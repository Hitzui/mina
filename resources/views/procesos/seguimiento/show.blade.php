<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>Seguimiento y reparaciones · {{ $ordenTrabajo->codigo }}</x-slot>
    <x-slot:headerFiles></x-slot><x-slot:footerFiles></x-slot>
    <div class="layout-top-spacing">
        <div class="d-flex justify-content-between flex-wrap gap-2"><h4>{{ $ordenTrabajo->codigo }} · Seguimiento y reparaciones</h4><a class="btn btn-light" href="{{ route('procesos.ordenes_trabajo.show', $ordenTrabajo) }}">Volver a la OT</a></div>
        @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @include('procesos.seguimiento._resumen')
        <p class="small text-muted">Las barras consultan el servidor automáticamente. Para renovar los registros y formularios de abajo, <a href="{{ route('procesos.ordenes_trabajo.seguimiento.show', $ordenTrabajo) }}">actualice esta pantalla</a>. Si alguien modifica un registro mientras lo edita, se verificará su versión antes de guardar.</p>
        <div class="alert alert-info"><strong>1. Poner en reparación:</strong> busque la asignación del equipo y elija reparación interna o envío a taller externo. Registre el inicio y deje el fin vacío para indicar que sigue reparándose.<br><strong>2. Terminar:</strong> en el registro de abajo indique cuándo volvió a funcionar y qué se hizo. El sistema calcula el tiempo.<br><strong>3. Costo:</strong> registre el importe total y la descripción del trabajo. Solo el intervalo coincidente con la asignación se pinta en rojo.</div>
        <div class="row g-3">
        @foreach($seguimiento['assignments'] as $uso)
            <div class="col-xl-6" id="asignacion-{{ $uso['id'] }}"><div class="card h-100"><div class="card-body">
                <h5>{{ $uso['code'] }} · {{ $uso['equipment'] }}</h5><p class="text-muted">{{ $uso['process'] }} · Asignación #{{ $uso['id'] }}</p>
                @include('procesos.seguimiento._barra', ['barra' => $uso, 'etiqueta' => $uso['equipment']])
                <p class="small mt-2">{{ str_replace('T', ' ', $uso['start']) }} — {{ $uso['end'] ? str_replace('T', ' ', $uso['end']) : 'Sin fin definido; se muestra hasta el momento del cálculo' }}<br><strong class="text-danger">{{ number_format($uso['hours_in_repair'], 2) }} h en reparación durante la asignación</strong></p>
                @if($uso['can_register'])
                <details class="border rounded p-3 mb-3"><summary><strong>Poner equipo en reparación</strong> · Registrar una reparación</summary>
                    <form class="mt-3" method="POST" action="{{ route('procesos.ordenes_trabajo.seguimiento.store', $ordenTrabajo) }}" data-confirm data-confirm-title="¿Registrar esta reparación?" data-confirm-text="Quedará vinculada a este equipo, proceso y OT. El horómetro y el plazo no se modifican.">
                        @csrf <input type="hidden" name="assignment_id" value="{{ $uso['id'] }}">
                        <label class="form-label d-block">¿Dónde se reparará?<select class="form-select" name="repair_location" required><option value="">Seleccione</option><option value="interna" @selected(old('repair_location') === 'interna')>Interna · personal propio</option><option value="externa" @selected(old('repair_location') === 'externa')>Externa · enviado a taller</option></select></label>
                        <label class="form-label">Inicio de la falla<input class="form-control" name="started_at" type="datetime-local" value="{{ old('started_at') }}" required></label>
                        <label class="form-label">Fin de la reparación (opcional)<input class="form-control" name="ended_at" type="datetime-local" value="{{ old('ended_at') }}"></label>
                        <label class="form-label d-block">Motivo<textarea class="form-control" name="reason" maxlength="1000" required>{{ old('reason') }}</textarea></label>
                        <label class="form-label d-block">Descripción de lo que se hizo (puede completar al terminar)<textarea class="form-control" name="work_description" maxlength="2000">{{ old('work_description') }}</textarea></label>
                        <button class="btn btn-primary mt-2">Guardar reparación del equipo</button>
                    </form>
                </details>
                <details class="border rounded p-3"><summary>Ampliar el plazo de esta asignación</summary>
                    <form method="POST" class="mt-3" action="{{ route('procesos.ordenes_trabajo.seguimiento.ampliar', [$ordenTrabajo, $uso['id']]) }}" data-confirm data-confirm-title="¿Ampliar el plazo?" data-confirm-text="Se verificará la disponibilidad del equipo. No se recalculan horas de trabajo ni costos históricos.">
                        @csrf @method('PUT')<input type="hidden" name="expected_end" value="{{ $uso['end'] }}">
                        <label class="form-label">Nuevo fin de asignación<input class="form-control" type="datetime-local" name="ended_at" required></label><button class="btn btn-outline-primary ms-2">Ampliar plazo</button>
                    </form>
                </details>
                @endif
            </div></div></div>
        @endforeach
        </div>
        <h5 class="mt-4">Reparaciones registradas</h5>
        @forelse($seguimiento['repairs'] as $r)
        <div class="card mb-3" id="reparacion-{{ $r['id'] }}"><div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2"><h6>#{{ $r['id'] }} · {{ $r['equipment'] }} · {{ $r['process'] }}</h6><span class="badge {{ $r['ended_at'] ? 'bg-success' : 'bg-danger' }}">{{ $r['ended_at'] ? 'Reparación terminada' : 'En reparación' }}</span></div>
            <p>{{ $r['reason'] }}</p><p class="small">{{ str_replace('T', ' ', $r['started_at']) }} — {{ $r['ended_at'] ? str_replace('T', ' ', $r['ended_at']) : 'Continúa' }} · {{ number_format($r['hours_in_assignment'], 2) }} h dentro de la asignación.</p>
            <p><strong>{{ match($r['repair_location']) { 'interna' => 'Reparación interna · personal propio', 'externa' => 'Reparación externa · enviado a taller', default => 'Lugar de reparación sin especificar' } }}</strong><br>Tiempo total {{ $r['ended_at'] ? 'de reparación' : 'transcurrido en reparación' }}: <strong>{{ number_format($r['total_hours'], 2) }} horas</strong>{{ $r['ended_at'] ? '' : ' (al momento de esta consulta)' }}.</p>
            @if($r['work_description'])<p><strong>Trabajo realizado:</strong> {{ $r['work_description'] }}</p>@endif
            @if($seguimiento['can_edit'])
            <details class="border rounded p-3 mb-2"><summary>Terminar o corregir el intervalo</summary><form class="row g-2 mt-2" method="POST" action="{{ route('procesos.ordenes_trabajo.seguimiento.update', [$ordenTrabajo, $r['id']]) }}" data-confirm data-confirm-title="¿Guardar los cambios de reparación?" data-confirm-text="Si otro dispositivo modificó este registro, primero deberá actualizar los datos.">
                @csrf @method('PUT')<input type="hidden" name="expected_version" value="{{ $r['version'] }}">
                <label class="col-12">¿Dónde se reparó o se está reparando?<select class="form-select" name="repair_location" required><option value="">Seleccione</option><option value="interna" @selected($r['repair_location'] === 'interna')>Interna · personal propio</option><option value="externa" @selected($r['repair_location'] === 'externa')>Externa · enviado a taller</option></select></label>
                <label class="col-md-4">Inicio<input class="form-control" type="datetime-local" name="started_at" value="{{ substr($r['started_at'], 0, 16) }}" required></label>
                <label class="col-md-4">Fin (vacío si continúa)<input class="form-control" type="datetime-local" name="ended_at" value="{{ substr($r['ended_at'] ?? '', 0, 16) }}"></label>
                <label class="col-12">Motivo de la falla<textarea class="form-control" name="reason" required maxlength="1000">{{ $r['reason'] }}</textarea></label>
                <label class="col-12">Descripción de lo que se hizo<textarea class="form-control" name="work_description" maxlength="2000">{{ $r['work_description'] }}</textarea></label><div><button class="btn btn-primary">Guardar reparación</button></div>
            </form></details>
            <form method="POST" action="{{ route('procesos.ordenes_trabajo.seguimiento.destroy', [$ordenTrabajo, $r['id']]) }}" data-confirm data-confirm-title="¿Anular este intervalo registrado por error?" data-confirm-text="Se conserva el historial. No se admite si tiene costos vigentes vinculados.">@csrf @method('DELETE')<input type="hidden" name="expected_version" value="{{ $r['version'] }}"><button class="btn btn-sm btn-outline-danger">Anular registro por error</button></form>
            @endif
            <h6 class="mt-3">Costos de esta reparación</h6>
            @can('movimientos_costo.view')@if(empty($r['costs']))<p class="small text-muted">Todavía no se ha registrado un costo para esta reparación.</p>@endif @endcan
            @foreach(collect($r['costs'])->groupBy('currency') as $moneda => $movimientos)<p><strong>Costo total registrado: {{ $moneda }} {{ number_format($movimientos->sum('amount'), 2) }}</strong></p>@endforeach
            @foreach($r['costs'] as $c)<p class="small mb-1">Movimiento #{{ $c['id'] }} · {{ $c['description'] }} · {{ $c['currency'] }} {{ number_format($c['amount'], 2) }} · {{ $c['amount_nio'] === null ? 'Equivalente pendiente' : 'C$ '.number_format($c['amount_nio'], 2) }}</p>@endforeach
            @if($r['can_cost'])
            <details class="border rounded p-3 mt-2"><summary>Registrar costo vinculado a esta reparación</summary>
                <p class="small mt-2">Se registra una sola vez en los costos del proceso {{ $r['process'] }} de {{ $ordenTrabajo->codigo }}. Para corregirlo o anularlo, abra los costos de ese proceso.</p>
                <form method="POST" class="row g-2" action="{{ route('procesos.ordenes_trabajo.seguimiento.costo', [$ordenTrabajo, $r['id']]) }}" data-confirm data-confirm-title="¿Registrar este costo de reparación?" data-confirm-text="Creará un movimiento de costo del proceso, vinculado a esta reparación. No registra una salida de caja automáticamente.">
                    @csrf
                    <label class="col-md-4">Categoría<select class="form-select" name="categoria_costo_id" required><option value="">Seleccione</option>@foreach($seguimiento['categories'] as $c)<option value="{{ $c['id'] }}">{{ $c['name'] }}</option>@endforeach</select></label>
                    <label class="col-md-4">Moneda<select class="form-select" name="moneda_id" required><option value="">Seleccione</option>@foreach($seguimiento['currencies'] as $m)<option value="{{ $m['id'] }}">{{ $m['name'] }}</option>@endforeach</select></label>
                    <label class="col-md-4">Fecha<input type="date" class="form-control" name="fecha" value="{{ now()->toDateString() }}" required></label>
                    <label class="col-md-8">Descripción de lo que se hizo<textarea class="form-control" name="descripcion" maxlength="255" placeholder="Ej.: cambio de rodamientos y ajuste del motor" required>{{ mb_substr($r['work_description'] ?? '', 0, 255) }}</textarea></label>
                    <label class="col-md-4">Costo total de reparación<input class="form-control" name="total_cost" type="number" step="0.01" min="0" required></label>
                    <div><button class="btn btn-primary">Registrar costo de reparación</button></div>
                </form>
            </details>
            @endif
        </div></div>
        @empty <p class="text-muted">Todavía no hay reparaciones registradas en las asignaciones de esta OT.</p> @endforelse
    </div>
</x-base-layout>
