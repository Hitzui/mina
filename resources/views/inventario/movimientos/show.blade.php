<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Detalle del Movimiento' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-8 col-lg-10 col-sm-12 layout-spacing">

            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h5 class="mb-1">
                            <i class="bi bi-arrow-left-right me-1"></i>
                            Movimiento #{{ $movimiento->id }}
                        </h5>

                        <p class="text-muted mb-0">
                            {{ $movimiento->fecha?->format('d/m/Y H:i') ?? '—' }}
                        </p>
                    </div>

                    <a
                        href="{{ route('inventario.movimientos.index') }}"
                        class="btn btn-light"
                    >
                        Volver
                    </a>
                </div>

                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label text-muted">Material</label>
                        <div class="form-control bg-light">
                            {{ $movimiento->producto?->nombre ?? 'Producto eliminado' }}
                            {{-- La ficha del material vive en un modal dentro
                                 del catálogo, y esa ruta devuelve json:
                                 enlazarla desde aqui abriria el json crudo
                                 en el navegador. --}}
                            <span class="text-muted d-block small">
                                <a href="{{ route('inventario.productos.index') }}">
                                    Ver en el catálogo
                                </a>
                            </span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Tipo</label>
                        <div class="pt-2">
                            @php
                                $clase = match (true) {
                                    $movimiento->esEntrada() => 'bg-success',
                                    $movimiento->esSalida() => 'bg-warning',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $clase }} fs-6">
                                {{ $movimiento->nombreTipo() }}
                            </span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Cantidad</label>
                        <div class="form-control bg-light fw-semibold">
                            {{ rtrim(rtrim(number_format((float) $movimiento->cantidad, 3), '0'), '.') }}
                            {{ $movimiento->producto?->unidad_medida }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Costo unitario</label>
                        <div class="form-control bg-light">
                            {{ number_format((float) $movimiento->costo_unitario, 2) }}
                            {{ $movimiento->moneda?->codigo }}
                        </div>

                        @if($movimiento->esSalida())
                            <div class="form-text">
                                Lo fija el costo promedio del almacén, no la pantalla.
                            </div>
                        @endif
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Total</label>
                        <div class="form-control bg-light fw-semibold">
                            {{ number_format((float) $movimiento->costo_total, 2) }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted">Equivalente en NIO</label>
                        <div class="form-control bg-light fw-semibold">
                            @if($movimiento->costo_total_nio === null)
                                <span class="text-muted">sin tipo de cambio</span>
                            @else
                                {{ number_format((float) $movimiento->costo_total_nio, 2) }}
                            @endif
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Imputado a</label>
                        <div class="form-control bg-light">
                            @if($movimiento->proceso_orden_id !== null)
                                Proceso:
                                {{ $movimiento->proceso_orden?->etapa?->nombre
                                    ?? $movimiento->proceso_orden?->codigo }}
                                <span class="text-muted">
                                    (materia prima de esa etapa)
                                </span>
                            @elseif($movimiento->orden_trabajo)
                                Orden: {{ $movimiento->orden_trabajo->codigo }}
                            @else
                                <span class="text-muted">Almacén</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted">Referencia</label>
                        <div class="form-control bg-light">
                            {{ $movimiento->referencia ?: '—' }}
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted">Observaciones</label>
                        <div class="form-control bg-light">
                            {{ $movimiento->observaciones ?: '—' }}
                        </div>
                    </div>

                </div>

                @if($movimiento->deleted_at)
                    <div class="alert alert-secondary mt-4" role="alert">
                        Este movimiento fue eliminado el
                        {{ $movimiento->deleted_at->format('d/m/Y H:i') }}
                        y las existencias se corrigieron en ese momento.
                    </div>
                @endif

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
