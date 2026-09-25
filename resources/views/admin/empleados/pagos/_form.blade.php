@if(isset($empleadoPago))
<form action="{{ route('admin.empleados.pagos.update', [$empleado, $empleadoPago]) }}" method="POST">
@method('PUT')
@else
<form action="{{ route('admin.empleados.pagos.store', $empleado) }}" method="POST">
@endif

    @csrf

    @if(isset($empleado))
        <div class="alert alert-light-primary border-0 mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-person-badge fs-4 me-2"></i>
                <div>
                    <strong>Empleado:</strong> {{ $empleado->nombre }}
                    <span class="text-muted ms-2">({{ $empleado->codigo }})</span>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <label for="tipo_pago_id" class="form-label">Tipo de pago <span
                    class="text-danger">*</span></label>
            <select id="tipo_pago_id" name="tipo_pago_id"
                    class="form-select select2 @error('tipo_pago_id') is-invalid @enderror"
                    data-select2-opciones='{"placeholder":"Buscar tipo de pago...","allowClear":true}'
                    required>
                <option value="">Seleccione...</option>
                @foreach($tiposPago as $tipoPago)
                    <option value="{{ $tipoPago->id }}"
                        @selected(old('tipo_pago_id', $empleadoPago->tipo_pago_id ?? '') == $tipoPago->id)>
                        {{ $tipoPago->nombre }}
                    </option>
                @endforeach
            </select>
            @error('tipo_pago_id')
            <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3">
            <label for="tarifa" class="form-label">Tarifa <span class="text-danger">*</span></label>
            <input type="number" id="tarifa" name="tarifa"
                   class="form-control @error('tarifa') is-invalid @enderror"
                   value="{{ old('tarifa', $empleadoPago->tarifa ?? '') }}"
                   min="0.01" step="0.01" required>
            @error('tarifa')
            <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3">
            <label for="moneda_id" class="form-label">Moneda <span class="text-danger">*</span></label>
            <select id="moneda_id" name="moneda_id"
                    class="form-select select2 @error('moneda_id') is-invalid @enderror"
                    data-select2-opciones='{"placeholder":"Buscar moneda...","allowClear":true}'
                    required>
                <option value="">Seleccione...</option>
                @foreach($monedas as $moneda)
                    <option value="{{ $moneda->id }}"
                        @selected(old('moneda_id', $empleadoPago->moneda_id ?? '') == $moneda->id)>
                        {{ $moneda->codigo }} - {{ $moneda->nombre }}
                    </option>
                @endforeach
            </select>
            @error('moneda_id')
            <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-6">
            <label for="fecha_inicio" class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
            <input type="text" id="fecha_inicio" name="fecha_inicio"
                   class="form-control @error('fecha_inicio') is-invalid @enderror"
                   value="{{ old('fecha_inicio', isset($empleadoPago) && $empleadoPago->fecha_inicio ? $empleadoPago->fecha_inicio->format('Y-m-d') : '') }}"
                   placeholder="Seleccione la fecha" autocomplete="off" required>
            @error('fecha_inicio')
            <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-6">
            <label for="fecha_fin" class="form-label">Fecha de finalización</label>
            <input type="text" id="fecha_fin" name="fecha_fin"
                   class="form-control @error('fecha_fin') is-invalid @enderror"
                   value="{{ old('fecha_fin', isset($empleadoPago) && $empleadoPago->fecha_fin ? $empleadoPago->fecha_fin->format('Y-m-d') : '') }}"
                   placeholder="Sin fecha de finalización" autocomplete="off">
            @error('fecha_fin')
            <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
            <label for="estado" class="form-label">Estado <span class="text-danger">*</span></label>
            <input type="hidden" name="estado" value="0">
            <div class="form-check form-switch form-switch-custom">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="estado" name="estado" value="1"
                    @checked(old('estado', $empleadoPago->estado ?? true))>
                <label class="form-check-label" for="estado">Activo</label>
            </div>
            @error('estado')
            <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="col-12">
            <label for="observaciones" class="form-label">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="4"
                      class="form-control @error('observaciones') is-invalid @enderror"
                      maxlength="500"
                      placeholder="Observaciones sobre la tarifa o condición de pago">{{ old('observaciones', $empleadoPago->observaciones ?? '') }}</textarea>
            @error('observaciones')
            <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="bi bi-x-lg me-1"></i> Cancelar
        </button>

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> {{ $submitText ?? 'Guardar' }}
        </button>
    </div>

</form>
