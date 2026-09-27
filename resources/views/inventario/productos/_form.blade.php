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

        {{--
            El 0 va por delante a proposito. El interruptor manda 0 o 1, y si
            el 1 estuviera primero, al desmarcarlo se quedaria sin marcar y
            el navegador no mandaria nada: el material se guardaria con el
            valor por defecto de la base en vez de inactivo.
        --}}
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

        <div class="form-text">
            Un material inactivo no se puede consumir ni registrar entradas.
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

    {{--
        Lo que hay en el almacen no se toca aqui: lo mueve cada movimiento
        del kardex. Se dice dentro del modal, no en la lista, porque es justo
        lo que alguien viene a buscar cuando entra a editar.
    --}}
    @if($editando)
        <div class="col-12">
            <div class="alert alert-light border mb-0 d-flex align-items-start gap-2">
                <i class="bi bi-info-circle"></i>
                <div class="small">
                    La existencia, el costo promedio y el valor en almacén
                    (<strong>{{ number_format($producto->existencia, 3) }}
                    {{ $producto->unidad_medida }}</strong>) no se cambian aquí:
                    los mueve cada entrada y cada consumo. Para corregirlos hay
                    que deshacer el movimiento y registrarlo de nuevo.
                </div>
            </div>
        </div>
    @endif

</div>
