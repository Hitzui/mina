{{--
    Campos del consumo de material de un proceso.

    El precio no se escribe: lo pone el promedio del almacen. Por eso el
    desplegable de material lleva la existencia y el costo de cada uno en
    sus data-*, y el js los muestra al elegir. Es solo una previsualizacion
    para no surprises: el valor que manda lo toma el servidor del saldo.

    El tipo tampoco se elige: desde aqui solo se consumen materiales. Meter
    una entrada de material imputada a un proceso no significaria nada y
    dejaria el costo del proceso sin tocar.
--}}
<div class="row g-3">

    <div class="col-md-7">
        <label for="materialProducto" class="form-label">
            Material <span class="text-danger">*</span>
        </label>

        <select
            name="producto_id"
            id="materialProducto"
            class="form-select select2"
            required
        >
            <option value="">Seleccione el material</option>

            @foreach($productos as $producto)
                <option
                    value="{{ $producto->id }}"
                    data-unidad="{{ $producto->unidad_medida }}"
                    data-existencia="{{ rtrim(rtrim(number_format($producto->existencia, 3), '0'), '.') ?: '0' }}"
                    data-costo="{{ number_format($producto->costo_promedio, 4) }}"
                >
                    {{ $producto->nombre }} ({{ $producto->unidad_medida }})
                    — hay {{ rtrim(rtrim(number_format($producto->existencia, 3), '0'), '.') ?: '0' }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-5">
        <label for="materialFecha" class="form-label">
            Fecha <span class="text-danger">*</span>
        </label>

        <input
            type="date"
            name="fecha"
            id="materialFecha"
            class="form-control"
            value="{{ old('fecha', $fechaPorDefecto) }}"
            required
        >
    </div>

    <div class="col-md-4">
        <label for="materialCantidad" class="form-label">
            Cantidad <span class="text-danger">*</span>
        </label>

        <input
            type="number"
            name="cantidad"
            id="materialCantidad"
            class="form-control"
            value="1"
            min="0.001"
            step="0.001"
            required
        >
    </div>

    {{--
        El costo unitario y el total se muestran pero no se escriben. Que el
        campo no se pueda rellenar a mano es la forma de que quede claro de
        que aqui no se decide el precio.
    --}}
    <div class="col-md-4">
        <label class="form-label text-muted">Costo unitario</label>

        <div class="input-group">
            <input
                type="text"
                id="materialCostoUnitario"
                class="form-control"
                value="0.0000"
                readonly
                tabindex="-1"
            >
            <span class="input-group-text" id="materialUnidad">—</span>
        </div>

        <small class="text-muted">
            Lo fija el costo promedio del almacén.
        </small>
    </div>

    <div class="col-md-4">
        <label class="form-label text-muted">Total en NIO</label>

        <input
            type="text"
            id="materialTotal"
            class="form-control fw-semibold"
            value="0.00"
            readonly
            tabindex="-1"
        >
    </div>

    <div class="col-12">
        <div class="alert d-none mb-0" id="materialAviso"></div>
    </div>

    <div class="col-md-8">
        <label for="materialObservaciones" class="form-label">
            Observaciones
        </label>

        <input
            type="text"
            name="observaciones"
            id="materialObservaciones"
            class="form-control"
            maxlength="1000"
            placeholder="Cemento para la pila 2, tanda de la tarde"
        >
    </div>

</div>
