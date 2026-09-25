@php
    $esEdicion = isset($etapa) && $etapa->exists;
@endphp

<form
    action="{{ $esEdicion
        ? route('admin.etapas.update', $etapa)
        : route('admin.etapas.store') }}"
    method="POST"
>
    @csrf

    @if($esEdicion)
        @method('PUT')
    @endif

    <div class="row">

        {{-- Nombre --}}
        <div class="col-md-8 mb-3">
            <label for="nombre" class="form-label">
                Nombre <span class="text-danger">*</span>
            </label>

            <input
                type="text"
                name="nombre"
                id="nombre"
                class="form-control @error('nombre') is-invalid @enderror"
                value="{{ old('nombre', $etapa->nombre ?? '') }}"
                maxlength="100"
                required
            >

            @error('nombre')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror
        </div>

        {{-- Orden --}}
        <div class="col-md-4 mb-3">
            <label for="orden" class="form-label">
                Orden <span class="text-danger">*</span>
            </label>

            <input
                type="number"
                name="orden"
                id="orden"
                class="form-control @error('orden') is-invalid @enderror"
                value="{{ old('orden', $etapa->orden ?? $siguienteOrden ?? 1) }}"
                min="1"
                step="1"
                required
            >

            @error('orden')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror
        </div>

        {{-- Descripción --}}
        <div class="col-12 mb-3">
            <label for="descripcion" class="form-label">
                Descripción
            </label>

            <textarea
                name="descripcion"
                id="descripcion"
                rows="4"
                class="form-control @error('descripcion') is-invalid @enderror"
                maxlength="65535"
            >{{ old('descripcion', $etapa->descripcion ?? '') }}</textarea>

            @error('descripcion')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror
        </div>

        {{-- Estado --}}
        <div class="col-md-4 mb-3">
            <label class="form-label d-block">
                Estado
            </label>

            <input type="hidden" name="estado" value="0">

            <div class="form-check form-switch">
                <input
                    type="checkbox"
                    name="estado"
                    id="estado"
                    value="1"
                    class="form-check-input @error('estado') is-invalid @enderror"
                    @checked(old('estado', $etapa->estado ?? true))
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

    </div>

    <div class="mt-4 d-flex justify-content-end gap-2">
        @if($esEdicion)
            <a
                href="{{ route('admin.etapas.index') }}"
                class="btn btn-light">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
        @else
            <a
                href="{{ route('admin.etapas.index') }}"
                class="btn btn-light">
                Cancelar
            </a>
        @endif


        <button
            type="submit"
            class="btn btn-primary"
        >
            <i class="fa-regular fa-floppy-disk"></i> {{ $esEdicion ? 'Actualizar Etapa' : 'Guardar Etapa' }}
        </button>

    </div>

</form>
