<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>{{ $title }}</x-slot:pageTitle>
    <x-breadcrumb :items="$breadcrumbs"/>
    <x-slot:headerFiles></x-slot>

    <div class="row layout-top-spacing">
        <div class="col-xl-10 col-lg-12">
            <div class="widget-content widget-content-area br-8">
                <h4 class="mb-1"><i class="bi bi-safe2 me-2"></i>Nuevo cierre de caja</h4>
                <p class="text-muted mb-4">El esperado es el saldo acumulado hasta el último día del período, incluidos saldos iniciales, cobros, entradas, egresos y transferencias. Cada método y moneda se calcula por separado; la diferencia es contado menos esperado.</p>

                @if ($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif

                <form method="GET" action="{{ route('procesos.cierres_caja.create') }}" class="row g-3 align-items-end mb-4">
                    <div class="col-12 col-md-4">
                        <label for="caja_id" class="form-label">Caja <span class="text-danger">*</span></label>
                        <select name="caja_id" id="caja_id" class="form-select" required>
                            <option value="">Seleccione una caja</option>
                            @foreach ($cajas as $caja)
                                <option value="{{ $caja->id }}" @selected(($filtros['caja_id'] ?? '') == $caja->id)>{{ $caja->nombre }}{{ $caja->estado ? '' : ' (inactiva)' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="fecha_desde" class="form-label">Desde <span class="text-danger">*</span></label>
                        <input type="text" id="fecha_desde" name="fecha_desde" class="form-control flatpickr-fecha" data-calendario="propio" value="{{ $filtros['fecha_desde'] ?? '' }}" placeholder="Seleccione fecha" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="fecha_hasta" class="form-label">Hasta <span class="text-danger">*</span></label>
                        <input type="text" id="fecha_hasta" name="fecha_hasta" class="form-control flatpickr-fecha" data-calendario="propio" value="{{ $filtros['fecha_hasta'] ?? '' }}" placeholder="Seleccione fecha" required>
                    </div>
                    <div class="col-12 col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-calculator me-1"></i>Calcular</button>
                    </div>
                </form>

                @if ($preview->isNotEmpty())
                    <form method="POST" action="{{ route('procesos.cierres_caja.store') }}">
                        @csrf
                        <input type="hidden" name="caja_id" value="{{ $filtros['caja_id'] }}">
                        <input type="hidden" name="fecha_desde" value="{{ $filtros['fecha_desde'] }}">
                        <input type="hidden" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] }}">
                        <h5 class="mb-3">Saldo esperado al cierre del período</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead><tr><th>Método de pago</th><th>Moneda</th><th class="text-end">Esperado</th><th class="text-end">Contado</th></tr></thead>
                                <tbody>
                                @foreach ($preview as $fila)
                                    @php($clave = $fila->metodo_pago_id . '|' . $fila->moneda_id)
                                    <tr>
                                        <td>{{ $fila->metodo }}</td>
                                        <td>{{ $fila->moneda }}</td>
                                        <td class="text-end">{{ number_format((float) $fila->monto_esperado, 2) }}</td>
                                        <td><input type="number" name="conteos[{{ $clave }}]" class="form-control text-end" min="0" step="0.01" value="{{ old('conteos.' . $clave) }}" required></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea id="observaciones" name="observaciones" class="form-control" rows="3" maxlength="5000">{{ old('observaciones') }}</textarea>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Registrar cierre</button>
                            <a href="{{ route('procesos.cierres_caja.index') }}" class="btn btn-light">Cancelar</a>
                        </div>
                    </form>
                @elseif (!empty($filtros['caja_id']) && !empty($filtros['fecha_desde']) && !empty($filtros['fecha_hasta']))
                    <div class="alert alert-info mb-0">No hay movimientos registrados hasta el final del período; no hay importes que cerrar.</div>
                @endif
            </div>
        </div>
    </div>

    <x-slot:footerFiles>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof flatpickr === 'undefined') return;
                flatpickr('.flatpickr-fecha', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y', locale: 'es' });
            });
        </script>
    </x-slot>
</x-base-layout>
