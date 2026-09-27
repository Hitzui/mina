@php
    $editando = isset($producto) && $producto->exists;
@endphp

<div class="row g-3">

    <div class="col-md-4">
        <label for="codigo" class="form-label">
            Código <span class="text-danger">*</span>
        </label>
        <input
            type="text"
            id="codigo"
            name="codigo"
            class="form-control text-uppercase @error('codigo') is-invalid @enderror"
            value="{{ old('codigo', $producto->codigo ?? '') }}"
            required
            maxlength="30"
        >
        @error('codigo')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-5">
        <label for="nombre" class="form-label">
            Material <span class="text-danger">*</span>
        </label>
        <input
            type="text"
            id="nombre"
            name="nombre"
            class="form-control @error('nombre') is-invalid @enderror"
            value="{{ old('nombre', $producto->nombre ?? '') }}"
            required
            maxlength="120"
        >
        @error('nombre')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3">
        <label for="unidad_medida" class="form-label">
            Unidad <span class="text-danger">*</span>
        </label>
        <input
            type="text"
            id="unidad_medida"
            name="unidad_medida"
            class="form-control @error('unidad_medida') is-invalid @enderror"
            value="{{ old('unidad_medida', $producto->unidad_medida ?? 'kg') }}"
            required
            maxlength="15"
            placeholder="kg, litro, tm..."
        >
        <div class="form-text">
            En qué se mide. Es lo que aparece junto a la cantidad.
        </div>
        @error('unidad_medida')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="categoria" class="form-label">
            Categoría
        </label>
        <input
            type="text"
            id="categoria"
            name="categoria"
            class="form-control @error('categoria') is-invalid @enderror"
            value="{{ old('categoria', $producto->categoria ?? '') }}"
            maxlength="60"
            placeholder="Cemento, Químicos..."
        >
        <div class="form-text">
            Solo para clasificar en el listado. No afecta al cálculo.
        </div>
        @error('categoria')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="stock_minimo" class="form-label">
            Mínimo en almacén
        </label>
        <input
            type="number"
            id="stock_minimo"
            name="stock_minimo"
            class="form-control @error('stock_minimo') is-invalid @enderror"
            value="{{ old('stock_minimo', $producto->stock_minimo ?? 0) }}"
            step="0.001"
            min="0"
        >
        <div class="form-text">
            Cuando las existencias bajen de aquí, el material se pinta en rojo
            en el listado. No impide consumir, solo avisa.
        </div>
        @error('stock_minimo')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="estado" class="form-label">
            Estado
        </label>

        {{-- El 0 va por delante: el interruptor manda 0 o 1 y el 1 --
             tiene que estar despues para que el 0 no se quede sin marcar --}}
        <input type="hidden" name="estado" value="0">

        <div class="form-check form-switch mt-2">
            <input
                type="checkbox"
                class="form-check-input @error('estado') is-invalid @enderror"
                id="estado"
                name="estado"
                value="1"
                @checked(old('estado', $producto->estado ?? true))
            >
            <label class="form-check-label" for="estado">
                Activo
            </label>
        </div>
        @error('estado')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="descripcion" class="form-label">
            Descripción
        </label>
        <textarea
            id="descripcion"
            name="descripcion"
            class="form-control @error('descripcion') is-invalid @enderror"
            rows="2"
            maxlength="255"
        >{{ old('descripcion', $producto->descripcion ?? '') }}</textarea>
        @error('descripcion')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check me-1"></i>
                {{ $editando ? 'Guardar cambios' : 'Crear material' }}
            </button>

            <a
                href="{{ route('inventario.productos.index') }}"
                class="btn btn-light"
            >
                Cancelar
            </a>
        </div>
    </div>

</div>
