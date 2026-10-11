<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles></x-slot>

    <div class="row layout-top-spacing">
        <div class="col-12">
            <div class="widget-content widget-content-area br-8">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <div>
                        <h4 class="mb-1"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reportes</h4>
                        <p class="text-muted mb-0">Consulta y descarga información del taller por período.</p>
                    </div>
                    @if ($exportar)
                        <div class="d-flex gap-2">
                            <a class="btn btn-outline-success" href="{{ route('reportes.exportar', ['formato' => 'xlsx'] + request()->query()) }}"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                            <a class="btn btn-outline-danger" href="{{ route('reportes.exportar', ['formato' => 'pdf'] + request()->query()) }}"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
                        </div>
                    @endif
                </div>

                <form method="GET" action="{{ route('reportes.index') }}" class="row g-3 align-items-end mb-4">
                    <input type="hidden" id="categoria" name="categoria" value="{{ $categoria }}">
                    <div class="col-lg-{{ $categoria === 'ordenes' ? '2' : '3' }} col-md-6">
                        <label for="selectorCategoria" class="form-label">Categoría</label>
                        <select class="form-select" id="selectorCategoria">
                            @foreach ($categorias as $clave => $datosCategoria)
                                <option value="{{ $clave }}" @selected($categoria === $clave)>{{ $datosCategoria['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-{{ $categoria === 'ordenes' ? '2' : '3' }} col-md-6">
                        <label for="tipo" class="form-label">Informe</label>
                        <select class="form-select" id="tipo" name="tipo">
                            @foreach ($categorias[$categoria]['reportes'] as $clave)
                                <option value="{{ $clave }}" @selected($tipo === $clave)>{{ $reportes[$clave] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label for="fecha_desde" class="form-label">Desde</label>
                        <input class="form-control" type="date" id="fecha_desde" name="fecha_desde" value="{{ $filtros['fecha_desde'] }}" required>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label for="fecha_hasta" class="form-label">Hasta</label>
                        <input class="form-control" type="date" id="fecha_hasta" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] }}" required>
                    </div>
                    @if ($categoria === 'ordenes')
                        <div class="col-lg-2 col-md-4">
                            <label for="orden" class="form-label">Código de orden</label>
                            <input class="form-control" type="search" id="orden" name="orden" value="{{ $filtros['orden'] }}" placeholder="Todas las órdenes">
                        </div>
                    @endif
                    <div class="col-lg-2 col-md-4">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Consultar</button>
                    </div>
                </form>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">{{ $reportes[$tipo] }}</h5>
                    <span class="text-muted small">{{ count($filas) }} registros</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle w-100">
                        <thead><tr>@foreach ($columnas as $columna)<th>{{ $columna }}</th>@endforeach</tr></thead>
                        <tbody>
                            @forelse ($filas as $fila)
                                <tr>@foreach ($fila as $valor)<td>{{ $valor }}</td>@endforeach</tr>
                            @empty
                                <tr><td colspan="{{ count($columnas) }}" class="text-center text-muted py-4">No hay registros para los filtros seleccionados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <x-slot:footerFiles>
        <script>
            (() => {
                const reportesPorCategoria = @json($reportesPorCategoria);
                const categoria = document.getElementById('categoria');
                const selectorCategoria = document.getElementById('selectorCategoria');
                const selectorReporte = document.getElementById('tipo');

                selectorCategoria.addEventListener('change', () => {
                    const seleccionados = reportesPorCategoria[selectorCategoria.value] || {};
                    categoria.value = selectorCategoria.value;
                    selectorReporte.replaceChildren();
                    Object.entries(seleccionados).forEach(([valor, etiqueta]) => {
                        selectorReporte.add(new Option(etiqueta, valor));
                    });
                });

                selectorReporte.addEventListener('change', () => {
                    for (const [clave, reportes] of Object.entries(reportesPorCategoria)) {
                        if (Object.hasOwn(reportes, selectorReporte.value)) {
                            categoria.value = clave;
                            selectorCategoria.value = clave;
                            break;
                        }
                    }
                });
            })();
        </script>
    </x-slot>
</x-base-layout>
