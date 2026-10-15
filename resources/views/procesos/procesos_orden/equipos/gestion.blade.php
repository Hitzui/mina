<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot>
    <x-slot:headerFiles></x-slot>
    <x-slot:footerFiles></x-slot>
    @php
        $uso = $procesoEquipo;
        $ruta = 'procesos.ordenes_trabajo.procesos.equipos.';
        $parametros = [$ordenTrabajo, $procesoOrden, $uso];
    @endphp
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><h5 class="mb-1">{{ $uso->equipo->codigo }} · {{ $uso->equipo->nombre }}</h5><span class="text-muted">{{ $ordenTrabajo->codigo }} / {{ $procesoOrden->codigo }}</span></div>
            <a class="btn btn-light" href="{{ route('procesos.ordenes_trabajo.procesos.show', [$ordenTrabajo, $procesoOrden]) }}">Volver al proceso</a>
        </div>
        <div class="card-body">
            @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div class="row g-3 mb-4">
                @foreach(['Depreciación' => $uso->depreciacion_total, 'Electricidad estimada' => $uso->costo_energia_nio, 'Insumos consumidos' => $uso->costo_insumos_nio, 'Total registrado' => $uso->costo_uso_nio] as $etiqueta => $importe)
                    <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">{{ $etiqueta }}</div><strong class="fs-5">C$ {{ number_format($importe, 2) }}</strong></div></div>
                @endforeach
            </div>
            <p><span class="badge bg-info">{{ $uso->estadoCosteo() }}</span> · {{ $uso->horas_efectivas === null ? 'Horas sin confirmar' : number_format($uso->horas_efectivas, 4).' horas efectivas' }}
                @if($uso->horas_efectivas > 0) · C$ {{ number_format($uso->costo_uso_nio / $uso->horas_efectivas, 2) }}/hora @endif
            </p>
            <p class="text-muted small">Asignación: {{ $uso->fecha_inicio?->format('d/m/Y H:i') }} — {{ $uso->fecha_fin?->format('d/m/Y H:i') ?? 'Sigue asignado' }}.
                Electricidad: {{ number_format($uso->potencia_kw_usada ?? 0, 4) }} kW × horas efectivas × C$ {{ number_format($uso->tarifa_kwh_nio ?? 0, 6) }}/kWh.
            </p>
            @can('proceso_equipo.edit')
            <form action="{{ route($ruta.'trabajo', $parametros) }}" method="POST" class="row g-3 border rounded p-3">
                @csrf @method('PUT')
                <h6>Trabajo efectivo</h6>
                <div class="col-md-4"><label class="form-label" for="horas">Total de horas trabajadas</label><input class="form-control" id="horas" type="number" name="horas_efectivas" value="{{ old('horas_efectivas', $uso->horas_efectivas) }}" min="0" step="0.0001"></div>
                <div class="col-md-4"><label class="form-label" for="horometroInicial">Horómetro inicial (opcional)</label><input class="form-control" id="horometroInicial" type="number" name="horometro_inicial" value="{{ old('horometro_inicial', $uso->horometro_inicial) }}" min="0" step="0.0001"></div>
                <div class="col-md-4"><label class="form-label" for="horometroFinal">Horómetro final (opcional)</label><input class="form-control" id="horometroFinal" type="number" name="horometro_final" value="{{ old('horometro_final', $uso->horometro_final) }}" min="0" step="0.0001"></div>
                <p class="text-muted small mb-0">Ingrese las horas totales o ambas lecturas del horómetro. Si completa ambos, deben coincidir. Las horas en blanco quedan pendientes y no generan costos nuevos por tiempo.</p>
                <div class="col-12"><input type="hidden" name="consumos_confirmados" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="consumos_confirmados" value="1" @checked(old('consumos_confirmados', $uso->consumos_confirmados))><span class="form-check-label">He registrado todos los insumos consumidos en este uso</span></label></div>
                <div class="col-12"><button class="btn btn-primary">Guardar trabajo efectivo</button></div>
            </form>
            @endcan
            @if($uso->recursos_estimados)
                <h6 class="mt-4">Referencia de consumo por horas efectivas</h6>
                @foreach($uso->recursos_estimados as $r)
                    <div class="small text-muted">{{ $r['nombre'] }}: {{ number_format($r['cantidad_por_hora'], 4) }} {{ $r['unidad'] }}/hora × {{ number_format($uso->horas_efectivas ?? 0, 4) }} h = {{ number_format($r['cantidad_por_hora'] * ($uso->horas_efectivas ?? 0), 4) }} {{ $r['unidad'] }} estimados.</div>
                @endforeach
                <p class="small text-muted">Referencia total con insumos estimados: C$ {{ number_format($uso->costo_estimado_nio, 2) }}. Los totales de proceso usan solamente consumos registrados.</p>
            @endif
        </div>
    </div>

    <div class="card mb-4"><div class="card-header"><h6 class="mb-0">Almacén y saldo en el equipo</h6></div><div class="card-body">
        @if($uso->entregas->isNotEmpty())
            <h6>Entregas originadas en este uso</h6>
            @foreach($uso->entregas as $entrega)
                <div class="small mb-1">#{{ $entrega->id }} · {{ $entrega->fecha->format('d/m/Y') }} · {{ $entrega->producto?->nombre_completo }} · Entregado {{ number_format($entrega->cantidad, 3) }} · Saldo actual {{ number_format($entrega->disponible, 3) }}</div>
            @endforeach
        @endif
        <p class="text-muted">Una entrega retira existencias del almacén y las mantiene en el equipo. Solo el consumo carga costo a este proceso; el sobrante conserva su valor para otros usos o para devolverlo.</p>
        @if($ordenTrabajo->estaCerrada() || $procesoOrden->estaCerrado())
            <div class="alert alert-info">Este proceso u orden está cerrado. Puede corregir horas y anular consumos existentes. Para agregar movimientos, reabra el proceso y la orden.</div>
        @else
        @can('proceso_equipo.edit') @can('movimientos_inventario.create')
        <form action="{{ route($ruta.'consumos', $parametros) }}" method="POST" class="row g-2 mb-4">
            @csrf <input type="hidden" name="tipo" value="entrega">
            <div class="col-md-5"><label class="form-label">Producto a entregar</label><select class="form-select" name="producto_id" required><option value="">Seleccione</option>@foreach($uso->equipo->recursos as $r)<option value="{{ $r->producto_id }}">{{ $r->producto?->nombre_completo }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Cantidad</label><input aria-label="Cantidad a entregar" class="form-control" type="number" name="cantidad" min="0.001" step="0.001" required></div>
            <div class="col-md-3"><label class="form-label">Fecha</label><input aria-label="Fecha de entrega" class="form-control" type="date" name="fecha" value="{{ now()->toDateString() }}" required></div>
            <div class="col-md-2 align-self-end"><button class="btn btn-outline-primary w-100">Entregar</button></div>
        </form>
        @endcan @endcan
        @endif
        @forelse($lotes as $lote)
            <div class="border rounded p-3 mb-3">
                <strong>{{ $lote->producto?->nombre }} · Saldo {{ number_format($lote->disponible, 4) }} {{ $lote->producto?->unidad_medida }}</strong>
                <div class="small text-muted mb-2">Entrega #{{ $lote->id }} del {{ $lote->fecha->format('d/m/Y') }} · C$ {{ number_format($lote->costo_unitario_nio, 4) }} por unidad · Valor pendiente C$ {{ number_format($lote->disponible * $lote->costo_unitario_nio, 2) }}</div>
                @if(!$ordenTrabajo->estaCerrada() && !$procesoOrden->estaCerrado()) @can('proceso_equipo.edit') @can('movimientos_inventario.create')
                <form action="{{ route($ruta.'consumos', $parametros) }}" method="POST" class="row g-2">
                    @csrf <input type="hidden" name="equipo_insumo_id" value="{{ $lote->id }}">
                    <div class="col-md-4"><select aria-label="Operación" class="form-select" name="tipo"><option value="consumo">Consumido en este proceso</option><option value="devolucion">Devolver al almacén</option></select></div>
                    <div class="col-md-3"><input aria-label="Cantidad" class="form-control" type="number" name="cantidad" min="0.001" max="{{ $lote->disponible }}" step="0.001" required></div>
                    <div class="col-md-3"><input aria-label="Fecha" class="form-control" type="date" name="fecha" value="{{ now()->toDateString() }}" min="{{ $lote->fecha->toDateString() }}" required></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Registrar</button></div>
                </form>
                @endcan @endcan @endif
            </div>
        @empty <p class="text-muted">No hay saldo de insumos en este equipo.</p> @endforelse
    </div></div>

    <div class="card"><div class="card-header">Consumos y devoluciones de este uso</div><div class="card-body table-responsive">
        <table class="table"><thead><tr><th>Fecha</th><th>Operación</th><th>Producto</th><th>Cantidad</th><th>Valor (NIO)</th><th></th></tr></thead><tbody>
            @forelse($uso->consumos as $consumo)<tr><td>{{ $consumo->fecha->format('d/m/Y') }}</td><td>{{ $consumo->tipo === 'consumo' ? 'Consumo del proceso' : 'Devolución al almacén' }}</td><td>{{ $consumo->insumo->producto?->nombre }}</td><td>{{ number_format($consumo->cantidad, 4) }}</td><td>{{ number_format($consumo->costo_total_nio, 2) }}</td><td>
                @if($consumo->tipo === 'consumo') @can('proceso_equipo.edit')
                <form method="POST" action="{{ route($ruta.'consumos.anular', [...$parametros, $consumo->id]) }}" data-confirm data-confirm-title="¿Anular este consumo?" data-confirm-text="La cantidad volverá al saldo del equipo y se retirará del costo del proceso.">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Anular consumo</button></form>
                @endcan @endif
            </td></tr>@empty <tr><td colspan="6">Todavía no se han registrado consumos.</td></tr>@endforelse
        </tbody></table>
    </div></div>
</x-base-layout>
