@csrf

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
            value="{{ old('nombre', $cliente->nombre ?? '') }}"
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
            value="{{ old('telefono', $cliente->telefono ?? '') }}"
            maxlength="30"
        >

        @error('telefono')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

    {{-- Dirección --}}
    <div class="col-md-12 mb-3">
        <label for="direccion" class="form-label">
            Dirección
        </label>

        <textarea
            id="direccion"
            name="direccion"
            class="form-control @error('direccion') is-invalid @enderror"
            maxlength="255"
            rows="3"
        >{{ old('direccion', $cliente->direccion ?? '') }}</textarea>

        @error('direccion')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

    {{-- Estado --}}
    <div class="col-md-6 mb-3">
        <label for="estado" class="form-label">
            Estado
        </label>

        <select
            id="estado"
            name="estado"
            class="form-select @error('estado') is-invalid @enderror"
        >
            <option value="1"
                {{ old('estado', $cliente->estado ?? 1) == 1 ? 'selected' : '' }}>
                Activo
            </option>

            <option value="0"
                {{ old('estado', $cliente->estado ?? 1) == 0 ? 'selected' : '' }}>
                Inactivo
            </option>
        </select>

        @error('estado')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

</div>

<div class="mt-4 d-flex gap-2">

    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-floppy-disk me-1"></i>
        {{ isset($cliente) ? 'Actualizar Cliente' : 'Guardar Cliente' }}
    </button>

    <a href="{{ route('admin.clientes.index') }}" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>
        Cancelar
    </a>

</div>
