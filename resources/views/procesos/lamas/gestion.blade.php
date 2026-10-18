<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot>
    <x-slot:headerFiles></x-slot><x-slot:footerFiles></x-slot>
    @php($ruta = 'procesos.ordenes_trabajo.lama.')
    <div class="card mt-4"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div><span class="badge bg-info">{{ $data['is_treatment'] ? 'OT de tratamiento' : 'OT de servicio' }}</span><h4 class="mt-2">{{ $ordenTrabajo->codigo }} · Lama y recuperación posterior</h4><p class="text-muted mb-0">{{ $ordenTrabajo->nombreTitular() }}</p></div>
            <div class="d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="{{ route('procesos.ordenes_trabajo.show', $ordenTrabajo) }}">{{ $data['is_treatment'] ? 'Abrir procesos y registrar trabajo' : 'Volver a la orden' }}</a><a class="btn btn-light" href="{{ route('procesos.lamas.index') }}">Todos los lotes</a></div>
        </div>
        @if($errors->any())<div class="alert alert-danger mt-3"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="alert alert-light border mt-3 mb-0">
            @if(!$data['is_treatment'])
                <strong>Cómo proceder</strong><ol class="mb-2"><li>En <a href="{{ route('procesos.ordenes_trabajo.show', $ordenTrabajo) }}#acuerdos">Acuerdos</a>, indique quién conserva la lama y qué porcentaje del oro posterior es de la empresa.</li><li>Registre cada lote físico con cantidad <strong>estimada</strong>, unidad y ubicación.</li><li>Cuando lo vaya a tratar, pulse <strong>Iniciar tratamiento del lote completo</strong>. Se crea una OT vinculada con su reparto; no se mezclan lotes ni se hacen retiros parciales.</li><li>En la nueva OT registre procesos, costos y oro recuperado; después valore ese oro.</li></ol>
                La tarifa del servicio y su cobro permanecen en esta OT. Conservar la lama no registra oro ni dinero automáticamente. Se pueden registrar los sobrantes de una OT ya finalizada, pero no de una cancelada.
            @else
                <strong>Pasos de este tratamiento</strong><ol class="mb-0"><li>Abra los procesos y registre personal, equipos, horas, químicos y demás costos.</li><li>Registre aquí los gramos recuperados <strong>antes de finalizar la OT</strong>. Puede registrar varias recuperaciones del mismo lote.</li><li>Valore cada recuperación usando el precio del oro. El sistema aplica el reparto histórico del lote para la consulta.</li><li>Revise el resultado referencial y, si necesita documentar el reparto físico, utilice Liquidaciones con el acuerdo heredado. La valoración no genera un cobro.</li></ol>
            @endif
        </div>
    </div></div>

    @if($data['can_register'])
    <div class="card mt-3"><div class="card-body"><h5>Registrar un lote de lama</h5>
        @if($data['agreements']->isEmpty())
            <div class="alert alert-info mb-0">Primero complete un acuerdo activo con las condiciones de la lama. <a href="{{ route('procesos.ordenes_trabajo.show', $ordenTrabajo) }}#acuerdos">Abrir acuerdos de esta OT</a>. Si no ve el botón para guardar un acuerdo, solicite esa operación al responsable autorizado.</div>
        @else
        <form method="POST" action="{{ route($ruta.'store', $ordenTrabajo) }}" class="row g-3">@csrf
            <div class="col-md-8"><label class="form-label">Acuerdo que respalda el lote</label><select class="form-select" name="acuerdo_id" required>@foreach($data['agreements'] as $a)<option value="{{ $a['id'] }}" @selected(old('acuerdo_id') == $a['id'])>{{ $a['label'] }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Fecha</label><input class="form-control" type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" min="{{ $ordenTrabajo->fecha->toDateString() }}" required></div>
            <div class="col-md-4"><label class="form-label">Cantidad estimada del lote completo</label><input class="form-control" type="number" name="cantidad_estimada" step="0.001" min="0.001" value="{{ old('cantidad_estimada') }}" required></div>
            <div class="col-md-3"><label class="form-label">Unidad</label><select class="form-select" name="unidad_id" required><option value="">Seleccione</option>@foreach($data['units'] as $u)<option value="{{ $u['id'] }}" @selected(old('unidad_id') == $u['id'])>{{ $u['name'] }}</option>@endforeach</select></div>
            <div class="col-md-5"><label class="form-label">Ubicación o depósito</label><input class="form-control" name="ubicacion" maxlength="200" value="{{ old('ubicacion') }}" placeholder="Ej. Patio, depósito 2" required></div>
            <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" maxlength="2000">{{ old('observaciones') }}</textarea></div>
            <p class="small text-muted mb-0">La cantidad es una referencia estimada, no un pesaje confirmado. Seleccione una unidad de peso. El lote conserva las condiciones vigentes al registrarlo, aunque el acuerdo se modifique después.</p>
            <div class="col-12"><button class="btn btn-primary">Guardar lote estimado</button></div>
        </form>
        @endif
    </div></div>
    @endif

    @forelse($data['lots'] as $lote)
    <div class="card mt-3"><div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2"><h5>{{ $lote['code'] }}</h5><span class="badge bg-info align-self-start">{{ $lote['status'] }}</span></div>
        <p><strong>Cantidad estimada:</strong> {{ number_format($lote['estimated_quantity'], 3) }} {{ $lote['unit'] }} · <strong>Ubicación:</strong> {{ $lote['location'] }}<br>Origen: <a href="{{ route($ruta.'show', $lote['source_id']) }}">{{ $lote['source_code'] }}</a> · {{ $lote['date'] }} · Conserva el material: {{ $lote['holder'] }}<br><strong>Oro de esta lama:</strong> empresa {{ $lote['company_percentage'] }}% / cliente {{ $lote['client_percentage'] }}%.</p>
        @if($lote['observations'])<p class="text-muted">{{ $lote['observations'] }}</p>@endif
        @if($lote['treatment_id'])
            <a class="btn btn-outline-primary" href="{{ route('procesos.ordenes_trabajo.show', $lote['treatment_id']) }}">Abrir {{ $lote['treatment_code'] }} · procesos y costos</a>
            @if(!$data['is_treatment'])<a class="btn btn-outline-primary" href="{{ route($ruta.'show', $lote['treatment_id']) }}">Recuperaciones y resultado</a>@endif
            <p class="small text-muted mt-2">El lote completo está vinculado a esta OT. No queda saldo para otro tratamiento, aunque la OT esté pendiente o cancelada.</p>
        @else
            @if($lote['holder'] === 'empresa' && $data['can_start'] && $ordenTrabajo->estado != 0)
            <form method="POST" action="{{ route($ruta.'iniciar', [$ordenTrabajo, $lote['id']]) }}" class="row g-2" data-confirm data-confirm-title="¿Iniciar tratamiento del lote completo?" data-confirm-text="Se creará una OT vinculada para toda la cantidad estimada. Este lote no podrá utilizarse en otra OT.">@csrf
                <div class="col-md-4"><label class="form-label">Fecha del tratamiento</label><input class="form-control" type="date" name="fecha" value="{{ now()->toDateString() }}" min="{{ $lote['date'] }}" required></div><div class="col-md-6 align-self-end"><button class="btn btn-primary">Iniciar tratamiento del lote completo</button></div>
            </form>
            @endif
            @if($data['can_edit'])
            <details class="mt-3"><summary>Corregir cantidad estimada o ubicación</summary><form method="POST" action="{{ route($ruta.'update', [$ordenTrabajo, $lote['id']]) }}" class="row g-2 mt-1">@csrf @method('PUT')
                <div class="col-md-3"><label class="form-label">Cantidad estimada</label><input class="form-control" name="cantidad_estimada" type="number" min="0.001" step="0.001" value="{{ $lote['estimated_quantity'] }}" required></div><div class="col-md-4"><label class="form-label">Ubicación</label><input class="form-control" name="ubicacion" maxlength="200" value="{{ $lote['location'] }}" required></div><div class="col-md-5"><label class="form-label">Observaciones</label><input class="form-control" name="observaciones" value="{{ $lote['observations'] }}" maxlength="2000"></div><div><button class="btn btn-outline-primary">Guardar corrección</button></div>
            </form></details>
            <form method="POST" action="{{ route($ruta.'destroy', [$ordenTrabajo, $lote['id']]) }}" class="mt-2" data-confirm data-confirm-title="¿Anular este lote?" data-confirm-text="Solo se puede anular si todavía no tiene tratamiento. Su registro se conserva en el historial.">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Anular lote registrado por error</button></form>
            @endif
        @endif
        @if($lote['treatment_id'] && $data['can_see_gold'])<p class="mt-3 mb-1">Oro recuperado: <strong>{{ number_format($lote['recovered_grams'], 4) }} g</strong> · Parte empresa: {{ number_format($lote['company_grams'], 4) }} g · Parte cliente: {{ number_format($lote['recovered_grams'] - $lote['company_grams'], 4) }} g. El reparto de gramos no aplica nuevamente la pureza.</p>@endif
        @if($lote['result'])
            <div class="alert alert-light border mt-3 mb-0"><strong>Tratamiento · referencia en NIO</strong><br>Valor conocido del oro de la empresa: C$ {{ number_format($lote['result']['company_gold_value_nio'], 2) }} · Costos conocidos: C$ {{ number_format($lote['result']['treatment_cost_nio'], 2) }}<br>Resultado referencial: {{ $lote['result']['reference_result_nio'] === null ? 'Pendiente de completar recuperación, valoración o costos' : 'C$ '.number_format($lote['result']['reference_result_nio'], 2) }}.<br><span class="small">La valoración no equivale a venta ni dinero cobrado. No suma otra vez los ingresos por venta del mismo oro.</span></div>
        @endif
        @if($data['is_treatment'])
            <h6 class="mt-4">Recuperaciones de este tratamiento</h6>
            @forelse($lote['recoveries'] as $r)<p class="small">#{{ $r['id'] }} · {{ $r['date'] }} · {{ $r['grams'] }} g · {{ $r['purity'] === null ? 'Pureza sin medir' : 'Pureza '.($r['purity'] * 100).'%' }} · {{ !$r['needs_valuation'] ? 'Valoración registrada' : ($r['valued'] ? 'Recalcular valoración: gramos/pureza corregidos o falta equivalente NIO' : 'Pendiente de valorar') }}</p>@empty<p class="text-muted">Aún no hay recuperaciones visibles para su usuario.</p>@endforelse
        @endif
    </div></div>
    @empty<div class="alert alert-info mt-3">Todavía no hay lotes. Complete el paso 1 y registre el material que realmente quedó.</div>@endforelse

    @if($data['can_recover'])
    <div class="card mt-3"><div class="card-body"><h5>Registrar oro recuperado</h5><p class="text-muted small">Registre cada resultado real del tratamiento; no use gramos estimados del acuerdo. Pureza opcional como fracción: 0.80 representa 80%.</p>
        <form method="POST" action="{{ route($ruta.'recuperar', $ordenTrabajo) }}" class="row g-3">@csrf
            <div class="col-md-4"><label class="form-label">Fecha</label><input class="form-control" type="date" name="fecha" value="{{ now()->toDateString() }}" required></div><div class="col-md-4"><label class="form-label">Gramos recuperados</label><input class="form-control" type="number" name="gramos" min="0" step="0.0001" required></div><div class="col-md-4"><label class="form-label">Pureza (0 a 1, opcional)</label><input class="form-control" type="number" name="pureza" min="0" max="1" step="0.000001"></div><div class="col-12"><button class="btn btn-primary">Guardar recuperación</button></div>
        </form>
    </div></div>
    @endif
    @if($data['can_value'] && $data['can_see_gold'])
    @php($pendientes = collect($data['lots'])->flatMap(fn ($l) => $l['recoveries'])->where('needs_valuation', true)->where('can_value', true))
    @if($pendientes->isNotEmpty())
    <div class="card mt-3"><div class="card-body"><h5>Valorar una recuperación</h5><p class="small text-muted">Elija en qué moneda está cargado el precio del oro. Se utilizará el último precio válido no posterior a la fecha y se guardará el valor en la moneda base. Primero registre precios y tipos de cambio si hacen falta.</p>
        <form method="POST" action="{{ route($ruta.'valorar', $ordenTrabajo) }}" class="row g-3" data-confirm data-confirm-title="¿Guardar la valoración?" data-confirm-text="Se calculará con el precio vigente para la fecha, la pureza registrada y el tipo de cambio aplicable. No genera cobro.">@csrf
            <div class="col-md-4"><label class="form-label">Recuperación</label><select class="form-select" name="recuperacion_id" required>@foreach($pendientes as $r)<option value="{{ $r['id'] }}">#{{ $r['id'] }} · {{ $r['grams'] }} g · {{ $r['date'] }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Fecha de valoración</label><input class="form-control" type="date" name="fecha" value="{{ now()->toDateString() }}" required></div><div class="col-md-4"><label class="form-label">Moneda del precio del oro</label><select class="form-select" name="precio_moneda" required>@foreach($data['currencies'] as $m)<option value="{{ $m['id'] }}">{{ $m['name'] }}</option>@endforeach</select></div><div class="col-12"><button class="btn btn-primary">Calcular y guardar valoración</button></div>
        </form>
    </div></div>@endif
    @endif
    @if($data['financial_summary'])
    @php($f = $data['financial_summary'])
    <div class="card mt-3"><div class="card-body"><h5>Servicio original y aprovechamiento posterior</h5>
        <div class="table-responsive"><table class="table"><tbody><tr><td>Ingresos registrados del servicio original</td><td>C$ {{ number_format($f['service_income_nio'], 2) }}</td></tr><tr><td>Costos conocidos del servicio original (una sola vez)</td><td>C$ {{ number_format($f['service_cost_nio'], 2) }}</td></tr><tr><td>Valor conocido del oro de la empresa, de los tratamientos vinculados</td><td>C$ {{ number_format($f['company_gold_value_nio'], 2) }}</td></tr><tr><td>Costos conocidos de esos tratamientos</td><td>C$ {{ number_format($f['treatment_cost_nio'], 2) }}</td></tr><tr><th>Resultado conjunto referencial</th><th>{{ $f['reference_result_nio'] === null ? 'Pendiente de completar los datos' : 'C$ '.number_format($f['reference_result_nio'], 2) }}</th></tr></tbody></table></div>
        <p class="text-muted small mb-0">Ingreso del servicio + valor del oro que corresponde a la empresa − costos del servicio − costos de tratamientos. No transfiere ni duplica los costos de molienda; no suma una venta del mismo oro además de su valoración. El resultado es referencial mientras el trabajo continúa; no es saldo de caja ni utilidad neta contable. Lotes disponibles pendientes de tratar: {{ $f['pending_lots'] }}.</p>
    </div></div>
    @endif
</x-base-layout>
