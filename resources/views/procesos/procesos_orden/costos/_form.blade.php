{{--
    Campos de un movimiento de costo. Lo usan el modal del proceso y el de
    la orden: son los mismos porque el importe se calcula igual en los dos
    casos, lo unico que cambia es de donde sale la orden y el proceso.

    El costo_unitario_nio y el costo_total_nio no se escriben: los calcula
    el servidor con el tipo de cambio de la fecha. Aqui solo se muestran.
--}}
<div class="row g-3">

    {{-- Categoría --}}
    <div class="col-md-4">
        <label for="costoCategoria" class="form-label">
            Categoría <span class="text-danger">*</span>
        </label>

        <select name="categoria_costo_id" id="costoCategoria" class="form-select select2" required>
            <option value="">Seleccione...</option>
            @foreach($categorias as $categoria)
                <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
            @endforeach
        </select>

        <small class="text-muted">
            No aparecen la mano de obra ni la depreciación: las calcula el
            sistema y escribirlas aquí las contaría dos veces.
        </small>
    </div>

    {{-- Fecha --}}
    <div class="col-md-4">
        <label for="costoFecha" class="form-label">
            Fecha <span class="text-danger">*</span>
        </label>

        <input
            type="date"
            name="fecha"
            id="costoFecha"
            class="form-control"
            value="{{ $fechaPorDefecto }}"
            required
        >
    </div>

    {{-- Moneda --}}
    <div class="col-md-4">
        <label for="costoMoneda" class="form-label">
            Moneda <span class="text-danger">*</span>
        </label>

        <select name="moneda_id" id="costoMoneda" class="form-select select2" required>
            @foreach($monedas as $moneda)
                <option
                    value="{{ $moneda->id }}"
                    data-base="{{ $moneda->es_moneda_base ? 1 : 0 }}"
                    @selected($moneda->es_moneda_base && old('moneda_id') === null)
                >
                    {{ $moneda->codigo }} — {{ $moneda->nombre }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Descripción --}}
    <div class="col-md-8">
        <label for="costoDescripcion" class="form-label">
            Descripción <span class="text-danger">*</span>
        </label>

        <input
            type="text"
            name="descripcion"
            id="costoDescripcion"
            class="form-control"
            maxlength="255"
            placeholder="Consumo de energía del molino, turno de la mañana"
            required
        >
    </div>

    {{--
        Los importes calculados. El total se previsualiza aqui para que se
        vea mientras se escribe, pero al guardar lo vuelve a calcular el
        servidor: es el que manda, y por eso no se manda en el formulario.
    --}}
    <div class="col-md-2">
        <label for="costoCantidad" class="form-label">
            Cantidad <span class="text-danger">*</span>
        </label>

        <input
            type="number"
            name="cantidad"
            id="costoCantidad"
            class="form-control"
            value="1"
            min="0.001"
            step="0.001"
            required
        >
    </div>

    <div class="col-md-2">
        <label for="costoUnitario" class="form-label">
            Costo unitario <span class="text-danger">*</span>
        </label>

        <input
            type="number"
            name="costo_unitario"
            id="costoUnitario"
            class="form-control"
            value="0.00"
            min="0"
            step="0.01"
            required
        >
    </div>

    <div class="col-md-4">
        <label class="form-label text-muted">Total</label>
        <div class="input-group">
            <input
                type="text"
                id="costoTotal"
                class="form-control fw-semibold"
                value="0.00"
                readonly
            >
            <span class="input-group-text" id="costoTotalMoneda">—</span>
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label text-muted">Total en NIO</label>
        <div class="input-group">
            <input
                type="text"
                id="costoTotalNio"
                class="form-control"
                value="—"
                readonly
            >
            <span class="input-group-text" id="costoTipoCambio">—</span>
        </div>
    </div>

    <div class="col-12">
        <div class="alert d-none mb-0" id="costoAviso"></div>
    </div>

    <div class="col-12">
        <label for="costoObservaciones" class="form-label">Observaciones</label>
        <textarea
            name="observaciones"
            id="costoObservaciones"
            class="form-control"
            rows="2"
        ></textarea>
    </div>
</div>
