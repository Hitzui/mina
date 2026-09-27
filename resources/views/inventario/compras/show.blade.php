<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title ?? 'Compra' }}
    </x-slot:pageTitle>

    <x-breadcrumb :items="$breadcrumbs"/>

    <x-slot:headerFiles>
    </x-slot>

    <div class="row layout-top-spacing">

        <div class="col-xl-9 col-lg-12 col-sm-12 layout-spacing">

            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">
                        <i class="bi bi-bag me-1"></i>
                        Compra {{ $compra->codigo }}
                    </h5>

                    <p class="text-muted mb-2">
                        {{ $compra->fecha?->format('d/m/Y') ?? '—' }}
                        @if($compra->numero_documento)
                            &middot; documento {{ $compra->numero_documento }}
                        @endif
                    </p>

                    {!! $compra->estadoEtiqueta() !!}
                </div>

                <div class="d-flex gap-2">
                    <a
                        href="{{ route('inventario.compras.edit', $compra) }}"
                        class="btn btn-light"
                    >
                        <i class="bi bi-pencil me-1"></i>
                        Editar
                    </a>

                    <a
                        href="{{ route('inventario.compras.index') }}"
                        class="btn btn-light"
                    >
                        Volver
                    </a>
                </div>
            </div>

            {{--
                El aviso de si el material esta en el almacen va aqui, en la
                ficha y no solo en la edicion, porque es la pregunta que se
                hace quien mira una compra: si esta ya en el almacen o no. El
                estado lo dice con una palabra, pero lo que de verdad importa
                es si el cemento llego o no.
            --}}
            @if($compra->esFinalizada())
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle me-1"></i>
                    Esta compra está finalizada: su material entró al almacén
                    en {{ $movimientos->min('fecha')?->format('d/m/Y H:i') ?? 'la fecha de la compra' }}.
                </div>
            @else
                <div class="alert alert-secondary" role="alert">
                    <i class="bi bi-info-circle me-1"></i>
                    Esta compra todavía <strong>no ha entrado al almacén</strong>.
                    El material entra cuando la compra pasa a
                    <strong>Finalizada</strong>.
                </div>
            @endif

            <div class="widget-content widget-content-area br-8 mb-4">

                <h6 class="mb-3">Datos de la compra</h6>

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label text-muted">Proveedor</label>
                        <div class="form-control bg-light">
                            {{ $compra->proveedor?->nombre_completo ?? 'Proveedor eliminado' }}
                            <span class="text-muted d-block small">
                                {{ $compra->proveedor?->codigo }}
                            </span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Moneda</label>
                        <div class="form-control bg-light">
                            {{ $compra->moneda?->codigo }} — {{ $compra->moneda?->nombre }}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted">Total</label>
                        <div class="form-control bg-light fw-semibold">
                            {{ number_format((float) $compra->total, 2) }}
                            {{ $compra->moneda?->codigo }}
                        </div>

                        @if($compra->total_nio !== null)
                            <div class="form-text">
                                {{ number_format((float) $compra->total_nio, 2) }} NIO
                            </div>
                        @else
                            <div class="form-text">
                                Sin tipo de cambio para esa fecha.
                            </div>
                        @endif
                    </div>

                    @if($compra->observaciones)
                        <div class="col-12">
                            <label class="form-label text-muted">Observaciones</label>
                            <div class="form-control bg-light">
                                {{ $compra->observaciones }}
                            </div>
                        </div>
                    @endif

                </div>

            </div>

            <div class="widget-content widget-content-area br-8 mb-4">

                <h6 class="mb-3">Material de la compra</h6>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Material</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end d-none d-sm-table-cell">Costo unitario</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($compra->detalles as $detalle)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            {{ $detalle->producto?->nombre ?? 'Material eliminado' }}
                                        </div>
                                        <div class="text-muted small font-monospace">
                                            {{ $detalle->producto?->codigo }}
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        {{ rtrim(rtrim(number_format((float) $detalle->cantidad, 3), '0'), '.') ?: '0' }}
                                        {{ $detalle->producto?->unidad_medida }}
                                    </td>
                                    <td class="text-end d-none d-sm-table-cell">
                                        {{ number_format((float) $detalle->costo_unitario, 2) }}
                                    </td>
                                    <td class="text-end fw-semibold">
                                        {{ number_format((float) $detalle->subtotal, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        Esta compra no tiene líneas de material.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($compra->detalles->isNotEmpty())
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="3" class="text-end text-muted">
                                        Subtotal
                                    </td>
                                    <td class="text-end">
                                        {{ number_format((float) $compra->subtotal, 2) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end text-muted">
                                        Impuesto
                                    </td>
                                    <td class="text-end">
                                        {{ number_format((float) $compra->impuesto, 2) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end fw-semibold">
                                        Total
                                    </td>
                                    <td class="text-end fw-bold">
                                        {{ number_format((float) $compra->total, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>

                </div>

            </div>

            @if($movimientos->isNotEmpty())
                {{--
                    Los movimientos que genero la compra. Se muestran para
                    que se vea de donde sale el stock: sin esta tabla, una
                    entrada en el kardex con la referencia de la compra no
                    dice si ya se aplico o si quedo a medias de una correccion.
                --}}
                <div class="widget-content widget-content-area br-8">

                    <h6 class="mb-3">
                        Entradas que esta compra generó al almacén
                    </h6>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Material</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end d-none d-sm-table-cell">Costo unitario</th>
                                    <th class="text-end d-none d-md-table-cell">Fecha</th>
                                    <th class="text-center">Ficha</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($movimientos as $movimiento)
                                    <tr>
                                        <td>
                                            {{ $movimiento->producto?->nombre ?? 'Material eliminado' }}
                                        </td>
                                        <td class="text-end">
                                            {{ rtrim(rtrim(number_format((float) $movimiento->cantidad, 3), '0'), '.') ?: '0' }}
                                            {{ $movimiento->producto?->unidad_medida }}
                                        </td>
                                        <td class="text-end d-none d-sm-table-cell">
                                            {{ number_format((float) $movimiento->costo_unitario, 2) }}
                                        </td>
                                        <td class="text-end d-none d-md-table-cell">
                                            {{ $movimiento->fecha?->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="text-center">
                                            <a
                                                href="{{ route('inventario.movimientos.show', $movimiento) }}"
                                                class="btn btn-sm btn-light"
                                                title="Ver el movimiento"
                                            >
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>

                </div>
            @endif

        </div>

    </div>

    <x-slot:footerFiles>
    </x-slot>

</x-base-layout>
