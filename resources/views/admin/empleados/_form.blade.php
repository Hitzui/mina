<div class="row">

    {{-- Nombre --}}
    <div class="col-md-6 mb-3">

        <label for="nombre" class="form-label">
            Nombre <span class="text-danger">*</span>
        </label>

        <input
            type="text"
            id="nombre"
            name="nombre"
            class="form-control @error('nombre') is-invalid @enderror"
            value="{{ old('nombre', $empleado->nombre ?? '') }}"
            maxlength="150"
            required
        >

        @error('nombre')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror

    </div>


    {{-- Teléfono --}}
    <div class="col-md-6 mb-3">

        <label for="telefono" class="form-label">
            Teléfono
        </label>

        <input
            type="text"
            id="telefono"
            name="telefono"
            class="form-control @error('telefono') is-invalid @enderror"
            value="{{ old('telefono', $empleado->telefono ?? '') }}"
            maxlength="30"
        >

        @error('telefono')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror

    </div>


    {{-- Modalidad de empleado --}}
    <div class="col-md-6 mb-3">

        <label for="tipo_empleado_id" class="form-label">
            Modalidad de empleado <span class="text-danger">*</span>
        </label>

        <select
            id="tipo_empleado_id"
            name="tipo_empleado_id"
            class="form-select @error('tipo_empleado_id') is-invalid @enderror"
            required
        >

            <option value="">
                Seleccione...
            </option>

            @foreach($tiposEmpleado as $tipo)

                <option
                    value="{{ $tipo->id }}"
                    {{ old(
                        'tipo_empleado_id',
                        $empleado->tipo_empleado_id ?? ''
                    ) == $tipo->id ? 'selected' : '' }}
                >
                    {{ $tipo->nombre }}
                </option>

            @endforeach

        </select>

        @error('tipo_empleado_id')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror

    </div>


    {{-- Fecha de ingreso --}}
    <div class="col-md-6 mb-3">

        <label for="fecha_ingreso" class="form-label">
            Fecha de ingreso
        </label>

        <input
            type="date"
            id="fecha_ingreso"
            name="fecha_ingreso"
            class="form-control @error('fecha_ingreso') is-invalid @enderror"
            value="{{ old(
                'fecha_ingreso',
                isset($empleado) && $empleado->fecha_ingreso
                    ? $empleado->fecha_ingreso->format('Y-m-d')
                    : ''
            ) }}"
        >

        @error('fecha_ingreso')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror

    </div>


    {{-- Estado --}}
    <div class="col-md-6 mb-3">

        <label for="estado" class="form-label">
            Estado <span class="text-danger">*</span>
        </label>

        <select
            id="estado"
            name="estado"
            class="form-select @error('estado') is-invalid @enderror"
            required
        >

            <option
                value="1"
                {{ old(
                    'estado',
                    $empleado->estado ?? 1
                ) == 1 ? 'selected' : '' }}
            >
                Activo
            </option>

            <option
                value="0"
                {{ old(
                    'estado',
                    $empleado->estado ?? 1
                ) == 0 ? 'selected' : '' }}
            >
                Inactivo
            </option>

        </select>

        @error('estado')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror

    </div>


    {{-- Observaciones --}}
    <div class="col-12 mb-3">

        <label for="observaciones" class="form-label">
            Observaciones
        </label>

        <textarea
            id="observaciones"
            name="observaciones"
            class="form-control @error('observaciones') is-invalid @enderror"
            rows="4"
        >{{ old('observaciones', $empleado->observaciones ?? '') }}</textarea>

        @error('observaciones')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror

    </div>

</div>


<div class="d-flex justify-content-end gap-2">
    @if(isset($empleado))
        <a href="{{ route('admin.empleados.show', $empleado) }}"
           class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
    @else
        <a href="{{ route('admin.empleados.index') }}"
           class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Cancelar
        </a>
    @endif


    <button type="submit" class="btn btn-primary">
        <i class="fa-regular fa-floppy-disk"></i> {{ isset($empleado) ? 'Actualizar' : 'Guardar' }}
    </button>

</div>
