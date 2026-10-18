<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot><x-slot:headerFiles></x-slot><x-slot:footerFiles></x-slot>
    <div class="card mt-4"><div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2"><h4>Lama y tratamientos</h4><a class="btn btn-light" href="{{ route('procesos.ordenes_trabajo.index') }}">Órdenes de trabajo</a></div>
        <p class="text-muted">Registre los lotes desde su OT de origen. Cada cantidad es estimada. Un lote completo alimenta una sola OT de tratamiento, sin mezclas ni retiros parciales.</p>
        <div class="table-responsive"><table class="table table-hover"><thead><tr><th>Lote</th><th>Origen</th><th>Cantidad estimada</th><th>Ubicación</th><th>Conserva</th><th>Oro empresa / cliente</th><th>Tratamiento</th></tr></thead><tbody>
        @forelse($lotes as $lote)<tr><td>{{ $lote->codigo }}</td><td><a href="{{ route('procesos.ordenes_trabajo.lama.show', $lote->orden_origen_id) }}">{{ $lote->origen->codigo }}</a></td><td>{{ number_format($lote->cantidad_estimada, 3) }} {{ $lote->unidad }}</td><td>{{ $lote->ubicacion }}</td><td>{{ ucfirst($lote->destino) }}</td><td>{{ $lote->porcentaje_empresa }}% / {{ 100 - $lote->porcentaje_empresa }}%</td><td>@if($lote->tratamiento)<a href="{{ route('procesos.ordenes_trabajo.lama.show', $lote->tratamiento) }}">{{ $lote->tratamiento->codigo }}</a>@else {{ $lote->destino === 'empresa' ? 'Disponible completo' : 'En poder del cliente' }} @endif</td></tr>
        @empty<tr><td colspan="7">Aún no hay lotes. Abra una OT, complete su acuerdo y entre a «Lama y recuperación posterior».</td></tr>@endforelse
        </tbody></table></div>{{ $lotes->links() }}
    </div></div>
</x-base-layout>
