<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles></x-slot>

    <div class="row layout-top-spacing">
        <div class="col-xl-10 col-lg-12">
            <div class="widget-content widget-content-area br-8">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
                    <div>
                        <h4 class="mb-1">Cierre {{ $cierre->codigo }}</h4>
                        <p class="text-muted mb-0">{{ $cierre->caja?->nombre ?? 'Caja eliminada' }} · {{ $cierre->fecha_desde->format('d/m/Y') }} al {{ $cierre->fecha_hasta->format('d/m/Y') }}</p>
                    </div>
                    <a href="{{ route('procesos.cierres_caja.index') }}" class="btn btn-light">Volver a cierres</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle">
                        <thead><tr><th>Método</th><th>Moneda</th><th class="text-end">Esperado</th><th class="text-end">Contado</th><th class="text-end">Diferencia</th></tr></thead>
                        <tbody>
                        @foreach ($cierre->detalles as $detalle)
                            <tr>
                                <td>{{ $detalle->metodoPago?->nombre ?? 'Método eliminado' }}</td>
                                <td>{{ $detalle->moneda?->codigo ?? 'Moneda eliminada' }}</td>
                                <td class="text-end">{{ number_format((float) $detalle->monto_esperado, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $detalle->monto_contado, 2) }}</td>
                                <td class="text-end {{ (float) $detalle->diferencia < 0 ? 'text-danger' : ((float) $detalle->diferencia > 0 ? 'text-warning' : 'text-success') }}">{{ number_format((float) $detalle->diferencia, 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($cierre->observaciones)<div class="border-top pt-3"><strong>Observaciones:</strong><p class="mb-0">{{ $cierre->observaciones }}</p></div>@endif
                <p class="text-muted small mb-0">Los importes son una captura histórica del saldo esperado hasta el final del período; las correcciones posteriores no alteran este cierre.</p>
            </div>
        </div>
    </div>
    <x-slot:footerFiles></x-slot>
</x-base-layout>
