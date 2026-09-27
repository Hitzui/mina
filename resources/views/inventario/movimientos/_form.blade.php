@php
    /*
     * Los tipos salen del modelo y no de una lista escrita aqui. En una
     * vista el nombre de la clase necesita ir completo: no hay un use que
     * lo resuelva y, sin la barra inicial, blade lo buscaria en el
     * namespace global.
    */
    $tiposDisponibles = [
        \App\Models\MovimientosInventario::TIPO_ENTRADA => 'Entrada',
        \App\Models\MovimientosInventario::TIPO_SALIDA => 'Salida sin proceso',
        \App\Models\MovimientosInventario::TIPO_AJUSTE_POSITIVO => 'Ajuste a favor',
        \App\Models\MovimientosInventario::TIPO_AJUSTE_NEGATIVO => 'Ajuste en contra',
    ];
@endphp

<div class="row g-3">

    <div class="col-md-6">
        <label for="producto_id" class="form-label">
            Material <span class="text-danger">*</span>
        </label>

        <select
            id="producto_id"
            name="producto_id"
            class="form-select @error('producto_id') is-invalid @enderror"
            required
        >
            <option value="">Seleccione el material</option>

            @foreach($productos as $producto)
                <option
                    value="{{ $producto->id }}"
                    @selected(old('producto_id') == $producto->id)
                >
                    {{ $producto->nombre }} ({{ $producto->unidad_medida }})
                    — hay {{ rtrim(rtrim(number_format($producto->existencia, 3), '0'), '.') ?: '0' }}
                </option>
            @endforeach
        </select>

        @error('producto_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3">
        <label for="tipo" class="form-label">
            Tipo <span class="text-danger">*</span>
        </label>

        <select
            id="tipo"
            name="tipo"
            class="form-select @error('tipo') is-invalid @enderror"
            required
        >
            @foreach($tiposDisponibles as $valor => $texto)
                <option value="{{ $valor }}" @selected(old('tipo', $valor) === $valor)>
                    {{ $texto }}
                </option>
            @endforeach
        </select>

        <div class="form-text">
            El consumo dentro de un proceso no se registra aquí: se registra
            desde la pantalla del proceso, para que la fila quede imputada a
            la etapa que lo gasta.
        </div>
        @error('tipo')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3">
        <label for="fecha" class="form-label">
            Fecha <span class="text-danger">*</span>
        </label>

        <input
            type="date"
            id="fecha"
            name="fecha"
            class="form-control @error('fecha') is-invalid @enderror"
            value="{{ old('fecha', now()->format('Y-m-d')) }}"
            required
        >
        @error('fecha')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3">
        <label for="cantidad" class="form-label">
            Cantidad <span class="text-danger">*</span>
        </label>

        <input
            type="number"
            id="cantidad"
            name="cantidad"
            class="form-control @error('cantidad') is-invalid @enderror"
            value="{{ old('cantidad') }}"
            step="0.001"
            min="0.001"
            required
        >
        @error('cantidad')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- El precio solo se pide para lo que entra. En una salida lo pone
         el promedio de lo que hay en el almacen. --}}
    <div class="col-md-3">
        <label for="costo_unitario" class="form-label">
            Costo unitario
        </label>

        <input
            type="number"
            id="costo_unitario"
            name="costo_unitario"
            class="form-control @error('costo_unitario') is-invalid @enderror"
            value="{{ old('costo_unitario') }}"
            step="0.0001"
            min="0"
        >
        @error('costo_unitario')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3">
        <label for="moneda_id" class="form-label">
            Moneda
        </label>

        <select
            id="moneda_id"
            name="moneda_id"
            class="form-select @error('moneda_id') is-invalid @enderror"
        >
            {{-- La columna no admite nulos, asi que vacio quiere decir la
                 moneda base. Se dice aqui para que no parezca que el
                 movimiento se queda sin moneda. --}}
            <option value="">NIO — moneda base</option>

            @foreach($monedas as $moneda)
                <option
                    value="{{ $moneda->id }}"
                    @selected(old('moneda_id') == $moneda->id)
                >
                    {{ $moneda->codigo }} — {{ $moneda->nombre }}
                    @unless($moneda->es_moneda_base)
                        (no es la base)
                    @endunless
                </option>
            @endforeach
        </select>

        <div class="form-text">
            El equivalente en NIO sale del tipo de cambio de esa fecha.
        </div>
        @error('moneda_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3">
        <label for="referencia" class="form-label">
            Referencia
        </label>

        <input
            type="text"
            id="referencia"
            name="referencia"
            class="form-control @error('referencia') is-invalid @enderror"
            value="{{ old('referencia') }}"
            maxlength="100"
            placeholder="Factura, nota de entrega..."
        >
        @error('referencia')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="observaciones" class="form-label">
            Observaciones
        </label>

        <textarea
            id="observaciones"
            name="observaciones"
            class="form-control @error('observaciones') is-invalid @enderror"
            rows="2"
            maxlength="1000"
        >{{ old('observaciones') }}</textarea>
        @error('observaciones')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check me-1"></i>
                Registrar movimiento
            </button>

            <a href="{{ route('inventario.movimientos.index') }}" class="btn btn-light">
                Cancelar
            </a>
        </div>
    </div>

</div>
