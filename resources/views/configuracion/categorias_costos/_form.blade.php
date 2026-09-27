@php
    $esEdicion = isset($categoriaCosto);
@endphp

<form
    id="formCategoriaCosto"
    method="POST"
    action="{{ $esEdicion
        ? route('configuracion.categorias_costos.update', $categoriaCosto)
        : route('configuracion.categorias_costos.store') }}"
>

    @csrf

    @if($esEdicion)
        @method('PUT')
    @endif

    <div class="row">

        {{-- Nombre --}}
        <div class="col-md-8 mb-4">

            <label for="nombre" class="form-label">
                Nombre
                <span class="text-danger">*</span>
            </label>

            <input
                type="text"
                class="form-control @error('nombre') is-invalid @enderror"
                id="nombre"
                name="nombre"
                value="{{ old('nombre', $categoriaCosto->nombre ?? '') }}"
                maxlength="100"
                required
                autofocus
            >

            @error('nombre')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror

        </div>


        {{-- Estado --}}
        <div class="col-md-4 mb-4">

            <label class="form-label d-block">
                Estado
            </label>

            {{-- Valor enviado cuando el switch está apagado --}}
            <input type="hidden" name="estado" value="0">

            <div class="form-check form-switch form-check-inline form-switch-info">

                <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    id="estado"
                    name="estado"
                    value="1"
                    {{ old('estado', $categoriaCosto->estado ?? 1) ? 'checked' : '' }}
                >

                <label class="form-check-label" for="estado">
                    Activo
                </label>

            </div>

            @error('estado')
            <div class="text-danger small mt-1">
                {{ $message }}
            </div>
            @enderror

        </div>


        {{-- Descripción --}}
        <div class="col-12 mb-4">

            <label for="descripcion" class="form-label">
                Descripción
            </label>

            <textarea
                class="form-control @error('descripcion') is-invalid @enderror"
                id="descripcion"
                name="descripcion"
                rows="4"
                maxlength="255"
            >{{ old('descripcion', $categoriaCosto->descripcion ?? '') }}</textarea>

            @error('descripcion')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror

        </div>

    </div>


    {{-- Botones --}}
    <div class="d-flex justify-content-end gap-2 mt-3">

       @if($esEdicion)
            <a
                href="{{ route('configuracion.categorias_costos.show', $categoriaCosto->id) }}"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-arrow-left"></i>
                Volver
            </a>
       @else
            <a
                href="{{ route('configuracion.categorias_costos.index') }}"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-x-lg me-1"></i>
                Cancelar
            </a>
       @endif

        <button
            type="submit"
            class="btn btn-primary"
        >
            <i class="bi bi-check-lg me-1"></i>

            {{ $esEdicion ? 'Actualizar' : 'Guardar' }}

        </button>

    </div>

</form>
