<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Informacion del Material' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-12 col-lg-12 col-sm-12 layout-spacing">

            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">
                            <i class="bi bi-box-seam me-1"></i>
                            {{ $producto->nombre }}
                        </h5>

                        <p class="text-muted mb-0 font-monospace">
                            {{ $producto->codigo }}
                        </p>
                    </div>

                    <div class="d-flex gap-2">
                        <a
                            href="{{ route('inventario.movimientos.create') }}"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-plus me-1"></i>
                            Registrar entrada
                        </a>

                        <a
                            href="{{ route('inventario.productos.index') }}"
                            class="btn btn-light"
                        >
                            Volver
                        </a>
                    </div>
                </div>

                @if($producto->descripcion)
                    <p class="text-muted">{{ $producto->descripcion }}</p>
                @endif

                {{-- Lo que hay en almacen --}}
                <div class="row g-3 mb-4">

                    <div class="col-md-3">
                        <label class="form-label text-muted">Existencia</label>

                        <div class="form-control bg-light fw-semibold fs-5
                            {{ $producto->existencia <= 0 ? 'text-muted' : '' }}">
                            {{ rtrim(rtrim(number_format($producto->existencia, 3), '0'), '.') ?: '0' }}
                            {{ $producto->unidad_medida }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Costo promedio</label>

                        <div class="form-control bg-light fw-semibold fs-5">
                            @if($producto->costo_promedio > 0)
                                {{ number_format($producto->costo_promedio, 4) }}
                            @else
                                <span class="text-muted">sin costo</span>
                            @endif
                        </div>

                        <div class="form-text">
                            Lo que fija el valor de cada salida del almacén.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Valor en almacén</label>

                        <div class="form-control bg-light fw-semibold fs-5">
                            {{ number_format($producto->valor_inventario, 2) }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Mínimo</label>

                        <div class="form-control bg-light fs-5">
                            {{ rtrim(rtrim(number_format($producto->stock_minimo, 3), '0'), '.') ?: '0' }}
                            {{ $producto->unidad_medida }}
                        </div>

                        @if($producto->estaPorDebajoDelMinimo())
                            <div class="form-text text-danger">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Está por debajo del mínimo: conviene reponer.
                            </div>
                        @endif
                    </div>

                </div>

                @if($producto->costo_promedio <= 0)
                    {{--
                        Sin costo cargado, cualquier consumo de este material
                        sumaria cero al costo del proceso sin avisar. Se dice
                        aqui y no solo en el momento del consumo, porque el
                        momento en que se registra la entrada es cuando se
                        puede arreglar.
                    --}}
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Este material no tiene costo cargado. Mientras no se
                        registre una entrada con su precio, cualquier consumo
                        suyo sumará <strong>cero</strong> al costo del proceso.
                    </div>
                @endif

                {{-- Los ultimos movimientos --}}
                <h6 class="mb-3">Últimos movimientos</h6>

                @if($movimientos->isEmpty())

                    <p class="text-muted">
                        Este material todavía no tiene movimientos en el almacén.
                    </p>

                @else

                    <div class="table-responsive">
                        <table class="table table-hover table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">Costo unitario</th>
                                    <th class="text-end">Total</th>
                                    <th>Destino</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($movimientos as $movimiento)
                                    <tr>
                                        <td>{{ $movimiento->fecha?->format('d/m/Y') ?? '—' }}</td>

                                        <td>
                                            @php
                                                $clase = match (true) {
                                                    $movimiento->esEntrada() => 'bg-success',
                                                    $movimiento->esSalida() => 'bg-warning',
                                                    default => 'bg-secondary',
                                                };
                                            @endphp
                                            <span class="badge {{ $clase }}">
                                                {{ $movimiento->nombreTipo() }}
                                            </span>
                                        </td>

                                        <td class="text-end fw-semibold">
                                            {{ rtrim(rtrim(number_format((float) $movimiento->cantidad, 3), '0'), '.') }}
                                        </td>

                                        <td class="text-end">
                                            {{ number_format((float) $movimiento->costo_unitario, 2) }}
                                        </td>

                                        <td class="text-end">
                                            {{ number_format((float) $movimiento->costo_total, 2) }}
                                        </td>

                                        <td>
                                            @if($movimiento->proceso_orden_id !== null)
                                                Proceso:
                                                {{ $movimiento->proceso_orden?->etapa?->nombre
                                                    ?? $movimiento->proceso_orden?->codigo }}
                                            @elseif($movimiento->orden_trabajo)
                                                Orden: {{ $movimiento->orden_trabajo->codigo }}
                                            @else
                                                <span class="text-muted">Almacén</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="text-muted small">
                        Se muestran los últimos 30 movimientos.
                        <a href="{{ route('inventario.movimientos.index', ['tipo' => null]) }}">
                            Ver el kardex completo
                        </a>.
                    </p>

                @endif

            </div>

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
