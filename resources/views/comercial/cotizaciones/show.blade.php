<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles>
        <link rel="stylesheet" href="{{ asset('plugins/sweetalerts2/sweetalerts2.css') }}">
        <style>
            @media print {
                .no-imprimir, .sidebar-wrapper, .header-container, .breadcrumb-container { display: none !important; }
                .main-container, .layout-px-spacing, .widget-content-area { margin: 0 !important; padding: 0 !important; }
                .cotizacion-documento { border: 0 !important; box-shadow: none !important; }
            }
        </style>
    </x-slot:headerFiles>

    <div class="row layout-top-spacing">
        <div class="col-12">
            <div class="widget-content widget-content-area br-8">
                @if($errors->any())
                    <div class="alert alert-danger no-imprimir">
                        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 no-imprimir">
                    <div>
                        <h4 class="mb-1">{{ $cotizacion->codigo }}</h4>
                        <div>{!! $cotizacion->estadoEtiqueta() !!}</div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-light" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
                        @if($cotizacion->estado === \App\Models\Cotizacion::ESTADO_BORRADOR)
                            @can('cotizaciones.edit')
                                <a href="{{ route('comercial.cotizaciones.edit', $cotizacion) }}" class="btn btn-warning"><i class="bi bi-pencil me-1"></i>Editar</a>
                                <form method="POST" action="{{ route('comercial.cotizaciones.enviar', $cotizacion) }}" class="js-confirmar-cotizacion" data-titulo="¿Marcar como enviada?" data-texto="La cotización quedará congelada para conservar la propuesta enviada al cliente.">
                                    @csrf
                                    <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Marcar enviada</button>
                                </form>
                            @endcan
                        @elseif($cotizacion->estado === \App\Models\Cotizacion::ESTADO_ENVIADA && ! $cotizacion->estaVencida())
                            @can('cotizaciones.edit')
                                <form method="POST" action="{{ route('comercial.cotizaciones.aceptar', $cotizacion) }}" class="js-confirmar-cotizacion" data-titulo="¿Aceptar la cotización?" data-texto="Se creará una orden de trabajo y se registrará este acuerdo comercial.">
                                    @csrf
                                    <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Aceptada por el cliente</button>
                                </form>
                                <form method="POST" action="{{ route('comercial.cotizaciones.rechazar', $cotizacion) }}" class="js-confirmar-cotizacion" data-titulo="¿Marcar como rechazada?" data-texto="La cotización quedará cerrada y no podrá aceptarse después.">
                                    @csrf
                                    <button class="btn btn-outline-danger"><i class="bi bi-x-lg me-1"></i>Rechazada</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>

                <article class="cotizacion-documento border rounded p-4 p-md-5">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 border-bottom pb-4 mb-4">
                        <div>
                            <h2 class="mb-1">Cotización de procesamiento</h2>
                            <div class="text-muted">{{ $cotizacion->codigo }}</div>
                        </div>
                        <div class="text-md-end">
                            <div><strong>Fecha:</strong> {{ $cotizacion->fecha->format('d/m/Y') }}</div>
                            <div><strong>Válida hasta:</strong> {{ $cotizacion->fecha_vencimiento->format('d/m/Y') }}</div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted text-uppercase">Cliente</h6>
                            <div class="fs-5 fw-semibold">{{ $cotizacion->cliente?->nombre ?? '—' }}</div>
                            @if($cotizacion->cliente?->telefono)<div>{{ $cotizacion->cliente->telefono }}</div>@endif
                            @if($cotizacion->cliente?->direccion)<div>{{ $cotizacion->cliente->direccion }}</div>@endif
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted text-uppercase">Mineral a procesar</h6>
                            <div class="fs-5 fw-semibold">{{ number_format((float) $cotizacion->peso_mineral, 3, ',', '.') }} {{ $cotizacion->unidad_peso }}</div>
                            @if($cotizacion->descripcion)<div class="mt-2">{{ $cotizacion->descripcion }}</div>@endif
                        </div>
                    </div>

                    <div class="card border mb-4">
                        <div class="card-body">
                            <h6 class="text-muted text-uppercase mb-3">Condición comercial</h6>
                            <div><strong>Tipo de participación:</strong> {{ $cotizacion->tipoParticipacion?->nombre ?? '—' }}</div>
                            @if($cotizacion->modalidad() === 'tarifa')
                                <div class="fs-4 fw-bold mt-2">{{ $cotizacion->moneda?->codigo }} {{ number_format((float) $cotizacion->tarifa_servicio, 2, ',', '.') }}</div>
                                <div class="text-muted">Tarifa fija total por el servicio de procesamiento.</div>
                            @else
                                <div class="row mt-2">
                                    <div class="col-sm-6"><strong>Cliente:</strong> {{ number_format((float) $cotizacion->porcentaje_cliente, 4, ',', '.') }}% del oro recuperado</div>
                                    <div class="col-sm-6"><strong>Empresa:</strong> {{ number_format((float) $cotizacion->porcentaje_empresa, 4, ',', '.') }}% del oro recuperado</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($cotizacion->observaciones)
                        <div class="mb-4">
                            <h6 class="text-muted text-uppercase">Condiciones y observaciones</h6>
                            <div class="text-break">{!! nl2br(e($cotizacion->observaciones)) !!}</div>
                        </div>
                    @endif

                    <div class="border-top pt-3 mt-5 text-muted small">
                        Esta propuesta es válida hasta el {{ $cotizacion->fecha_vencimiento->format('d/m/Y') }}. La aceptación genera una orden de trabajo con los datos y condiciones aquí descritos.
                    </div>

                    @if($cotizacion->ordenTrabajo)
                        <div class="alert alert-success mt-4 mb-0 no-imprimir">
                            Orden creada al aceptar: <a href="{{ route('procesos.ordenes_trabajo.show', $cotizacion->ordenTrabajo) }}"><strong>{{ $cotizacion->ordenTrabajo->codigo }}</strong></a>
                        </div>
                    @endif
                </article>
            </div>
        </div>
    </div>

    <x-slot:footerFiles>
        <script src="{{ asset('plugins/sweetalerts2/sweetalerts2.min.js') }}"></script>
        <script>
            document.querySelectorAll('.js-confirmar-cotizacion').forEach(form => {
                form.addEventListener('submit', event => {
                    event.preventDefault();
                    Swal.fire({
                        title: form.dataset.titulo,
                        text: form.dataset.texto,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Continuar',
                        cancelButtonText: 'Cancelar',
                    }).then(result => {
                        if (result.isConfirmed) form.submit();
                    });
                });
            });
        </script>
    </x-slot:footerFiles>
</x-base-layout>
