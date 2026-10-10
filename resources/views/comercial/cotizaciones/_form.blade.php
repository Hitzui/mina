@php
    $esEdicion = $cotizacion->exists;
    $modalidadActual = old('modalidad', $cotizacion->modalidad());
    $fecha = old('fecha', $cotizacion->fecha?->format('Y-m-d') ?? today()->format('Y-m-d'));
    $vencimiento = old('fecha_vencimiento', $cotizacion->fecha_vencimiento?->format('Y-m-d') ?? today()->addDays(30)->format('Y-m-d'));
@endphp

<form method="POST" action="{{ $esEdicion ? route('comercial.cotizaciones.update', $cotizacion) : route('comercial.cotizaciones.store') }}" id="formCotizacion">
    @csrf
    @if($esEdicion) @method('PUT') @endif

    <div class="row g-3">
        @if($esEdicion)
            <div class="col-md-4">
                <label class="form-label">Código</label>
                <input class="form-control" value="{{ $cotizacion->codigo }}" readonly>
            </div>
        @endif
        <div class="col-md-4">
            <label for="fecha" class="form-label">Fecha de cotización <span class="text-danger">*</span></label>
            <input type="date" class="form-control @error('fecha') is-invalid @enderror" id="fecha" name="fecha" value="{{ $fecha }}" required>
            @error('fecha')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label for="fecha_vencimiento" class="form-label">Válida hasta <span class="text-danger">*</span></label>
            <input type="date" class="form-control @error('fecha_vencimiento') is-invalid @enderror" id="fecha_vencimiento" name="fecha_vencimiento" value="{{ $vencimiento }}" required>
            @error('fecha_vencimiento')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-8">
            <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
            <select class="form-select select2 @error('cliente_id') is-invalid @enderror" id="cliente_id" name="cliente_id" required>
                <option value="">Seleccione un cliente</option>
                @foreach($clientes as $cliente)
                    <option value="{{ $cliente->id }}" @selected((string) old('cliente_id', $cotizacion->cliente_id) === (string) $cliente->id)>{{ $cliente->nombre }}</option>
                @endforeach
            </select>
            @error('cliente_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label for="peso_mineral" class="form-label">Peso de mineral <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="number" class="form-control @error('peso_mineral') is-invalid @enderror" id="peso_mineral" name="peso_mineral" min="0.001" step="0.001" value="{{ old('peso_mineral', $cotizacion->peso_mineral) }}" required>
                <select class="form-select select2" name="unidad_peso_id" id="unidad_peso_id" required>
                    <option value="">Unidad</option>
                    @foreach($unidadesMedida as $unidad)
                        <option value="{{ $unidad->id }}" @selected((string) old('unidad_peso_id', $cotizacion->unidad_peso_id) === (string) $unidad->id)>{{ $unidad->nombre }}</option>
                    @endforeach
                </select>
            </div>
            @error('peso_mineral')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @error('unidad_peso_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label for="descripcion" class="form-label">Descripción del procesamiento</label>
            <textarea class="form-control @error('descripcion') is-invalid @enderror" id="descripcion" name="descripcion" rows="2" maxlength="5000">{{ old('descripcion', $cotizacion->descripcion) }}</textarea>
            @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label for="tipo_participacion_id" class="form-label">Tipo de participación <span class="text-danger">*</span></label>
            <select class="form-select select2 @error('tipo_participacion_id') is-invalid @enderror" name="tipo_participacion_id" id="tipo_participacion_id" required>
                <option value="">Seleccione un tipo</option>
                @foreach($tiposParticipacion as $tipo)
                    <option value="{{ $tipo->id }}" @selected((string) old('tipo_participacion_id', $cotizacion->tipo_participacion_id) === (string) $tipo->id)>{{ $tipo->nombre }}</option>
                @endforeach
            </select>
            @error('tipo_participacion_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label d-block">Condición comercial <span class="text-danger">*</span></label>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="modalidad" id="modalidadPorcentajes" value="porcentajes" @checked($modalidadActual === 'porcentajes')>
                <label class="form-check-label" for="modalidadPorcentajes">Participación en oro</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="modalidad" id="modalidadTarifa" value="tarifa" @checked($modalidadActual === 'tarifa')>
                <label class="form-check-label" for="modalidadTarifa">Tarifa fija total</label>
            </div>
            @error('modalidad')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6 campos-porcentajes">
            <label for="porcentaje_cliente" class="form-label">Participación del cliente <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="number" class="form-control @error('porcentaje_cliente') is-invalid @enderror" id="porcentaje_cliente" name="porcentaje_cliente" min="0" max="100" step="0.0001" value="{{ old('porcentaje_cliente', $cotizacion->porcentaje_cliente) }}">
                <span class="input-group-text">%</span>
            </div>
            @error('porcentaje_cliente')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 campos-porcentajes">
            <label for="porcentaje_empresa" class="form-label">Participación de la empresa <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="number" class="form-control @error('porcentaje_empresa') is-invalid @enderror" id="porcentaje_empresa" name="porcentaje_empresa" min="0" max="100" step="0.0001" value="{{ old('porcentaje_empresa', $cotizacion->porcentaje_empresa) }}">
                <span class="input-group-text">%</span>
            </div>
            @error('porcentaje_empresa')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6 campos-tarifa">
            <label for="tarifa_servicio" class="form-label">Importe total fijo <span class="text-danger">*</span></label>
            <input type="number" class="form-control @error('tarifa_servicio') is-invalid @enderror" id="tarifa_servicio" name="tarifa_servicio" min="0.01" step="0.01" value="{{ old('tarifa_servicio', $cotizacion->tarifa_servicio) }}">
            @error('tarifa_servicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 campos-tarifa">
            <label for="moneda_selector" class="form-label">Moneda de la tarifa <span class="text-danger">*</span></label>
            <select class="form-select select2" id="moneda_selector">
                <option value="">Seleccione una moneda</option>
                @foreach($monedas as $moneda)
                    <option value="{{ $moneda->id }}" @selected((string) old('moneda_id', $cotizacion->moneda_id ?? $monedaBase?->id) === (string) $moneda->id)>{{ $moneda->codigo }} — {{ $moneda->nombre }}</option>
                @endforeach
            </select>
            @error('moneda_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <input type="hidden" name="moneda_id" id="moneda_id" value="{{ old('moneda_id', $monedaBase?->id) }}">

        <div class="col-12">
            <label for="observaciones" class="form-label">Condiciones y observaciones</label>
            <textarea class="form-control @error('observaciones') is-invalid @enderror" id="observaciones" name="observaciones" rows="3" maxlength="5000">{{ old('observaciones', $cotizacion->observaciones) }}</textarea>
            @error('observaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="alert alert-light border mt-4 mb-0">
        En participación, los porcentajes del cliente y la empresa deben sumar 100%. La tarifa fija es un importe total en la moneda elegida. Al enviar, la propuesta queda congelada; si la aceptan dentro de su vigencia, se crea la orden y se copia este acuerdo.
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="{{ route('comercial.cotizaciones.index') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>{{ $esEdicion ? 'Guardar cambios' : 'Guardar borrador' }}</button>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const radioPorcentajes = document.getElementById('modalidadPorcentajes');
        const radioTarifa = document.getElementById('modalidadTarifa');
        const selectorMoneda = document.getElementById('moneda_selector');
        const monedaHidden = document.getElementById('moneda_id');
        const modalidad = () => {
            const tarifa = radioTarifa.checked;
            document.querySelectorAll('.campos-porcentajes').forEach(el => el.classList.toggle('d-none', tarifa));
            document.querySelectorAll('.campos-tarifa').forEach(el => el.classList.toggle('d-none', !tarifa));
            document.getElementById('porcentaje_cliente').required = !tarifa;
            document.getElementById('porcentaje_empresa').required = !tarifa;
            document.getElementById('tarifa_servicio').required = tarifa;
            selectorMoneda.required = tarifa;
            selectorMoneda.disabled = !tarifa;
            if (tarifa) {
                selectorMoneda.name = 'moneda_id';
                monedaHidden.disabled = true;
                selectorMoneda.value = monedaHidden.value;
            } else {
                selectorMoneda.removeAttribute('name');
                monedaHidden.disabled = false;
            }
        };
        [radioPorcentajes, radioTarifa].forEach(radio => radio.addEventListener('change', modalidad));
        selectorMoneda.addEventListener('change', () => monedaHidden.value = selectorMoneda.value);
        modalidad();
    });
</script>
