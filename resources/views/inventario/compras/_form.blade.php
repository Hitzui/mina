{{--
    Formulario de una compra: cabecera y lineas de material.

    Las lineas van en una rejilla y no en un modal porque una compra lleva
    las que lleve: meter y quitar filas dentro de una ventana pequena es
    incomodo, y el total de abajo tiene que verse mientras se rellena.

    Los totales no se escriben. El subtotal sale de la suma de las lineas y
    el impuesto se aplica sobre ese subtotal, y los dos los calcula el
    servidor: si vinieran del formulario, el almacen recibiria el material
    por un importe que no es el de su linea.
--}}
<form method="POST"
      action="{{ $esEdicion ? route('inventario.compras.update', $compra) : route('inventario.compras.store') }}"
      id="formCompra">

    @csrf

    @if($esEdicion)
        @method('PUT')
    @endif

    <div class="row g-3">

        {{-- ============================ la cabecera ============================ --}}

        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                    <h6 class="mb-3">Datos de la compra</h6>

                    <div class="row g-3">

                        {{--
                            El proveedor se elige en un modal y no en un
                            desplegable. El catalogo de proveedores crece sin
                            limite y con un desplegable habia que abrirlo y
                            recorrerlo entero en un telefono, sin buscar nada.
                        --}}
                        @include('inventario.compras._campo_proveedor')

                        <div class="col-md-3">
                            <label for="fecha" class="form-label">
                                Fecha <span class="text-danger">*</span>
                            </label>
                            <input
                                type="date"
                                id="fecha"
                                name="fecha"
                                class="form-control @error('fecha') is-invalid @enderror"
                                value="{{ old('fecha', $compra->fecha?->format('Y-m-d') ?? today()->format('Y-m-d')) }}"
                                required
                            >
                            @error('fecha')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <label for="numero_documento" class="form-label">
                                Documento
                            </label>
                            <input
                                type="text"
                                id="numero_documento"
                                name="numero_documento"
                                class="form-control @error('numero_documento') is-invalid @enderror"
                                value="{{ old('numero_documento', $compra->numero_documento) }}"
                                maxlength="60"
                                placeholder="Factura"
                            >
                            @error('numero_documento')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="moneda_id" class="form-label">
                                Moneda <span class="text-danger">*</span>
                            </label>
                            <select
                                id="moneda_id"
                                name="moneda_id"
                                class="form-select select2 @error('moneda_id') is-invalid @enderror"
                                required
                            >
                                @foreach($monedas as $moneda)
                                    <option
                                        value="{{ $moneda->id }}"
                                        data-base="{{ $moneda->es_moneda_base ? 1 : 0 }}"
                                        @selected(old('moneda_id', $compra->moneda_id ?? null) == $moneda->id)
                                    >
                                        {{ $moneda->codigo }} — {{ $moneda->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            @error('moneda_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="porcentaje_impuesto" class="form-label">
                                Impuesto (%)
                            </label>
                            <input
                                type="number"
                                id="porcentaje_impuesto"
                                name="porcentaje_impuesto"
                                class="form-control @error('porcentaje_impuesto') is-invalid @enderror"
                                value="{{ old('porcentaje_impuesto') }}"
                                step="0.01"
                                min="0"
                                max="100"
                                placeholder="0"
                            >
                            <div class="form-text">
                                Se aplica sobre el subtotal. El total lo calcula el servidor.
                            </div>
                            @error('porcentaje_impuesto')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="estado" class="form-label">
                                Estado <span class="text-danger">*</span>
                            </label>
                            <select
                                id="estado"
                                name="estado"
                                class="form-select @error('estado') is-invalid @enderror"
                                required
                            >
                                @foreach(\App\Models\Compra::ESTADOS as $numero => $datos)
                                    <option
                                        value="{{ $numero }}"
                                        @selected(old('estado', $compra->estado ?? \App\Models\Compra::ESTADO_PENDIENTE) == $numero)
                                    >
                                        {{ $datos['texto'] }}
                                    </option>
                                @endforeach
                            </select>

                            {{--
                                El estado decide si el material entra al almacen, y es
                                lo que mas conviene ver al elegirlo. Se avisa aqui y no
                                solo al guardar, para que nadie descubra despues que
                                dejo la compra en pendiente y el cemento no llego.
                            --}}
                            <div class="form-text" id="avisoEstado">
                                El material entra al almacén solo al finalizar la compra.
                            </div>
                            @error('estado')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="observaciones" class="form-label">
                                Observaciones
                            </label>
                            <textarea
                                id="observaciones"
                                name="observaciones"
                                class="form-control @error('observaciones') is-invalid @enderror"
                                rows="2"
                                maxlength="1000"
                            >{{ old('observaciones', $compra->observaciones) }}</textarea>
                            @error('observaciones')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- ============================ las lineas ============================ --}}

        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="mb-1">Material de la compra</h6>
                            <p class="text-muted mb-0 small">
                                Cada línea es un material que entra al almacén al finalizar.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="btn btn-sm btn-light"
                            id="btnAgregarLinea"
                        >
                            <i class="bi bi-plus-lg me-1"></i>
                            Agregar línea
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle" id="tablaLineas">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 45%">Material</th>
                                    <th style="width: 20%">Cantidad</th>
                                    <th style="width: 20%">Costo unitario</th>
                                    <th style="width: 12%" class="text-end">Subtotal</th>
                                    <th style="width: 3%"></th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoLineas">
                                {{--
                                    Las lineas existentes las pinta el js, leyendo
                                    los datos de la compra. Se escribe aqui una
                                    fila de ejemplo para que el script tenga un
                                    molde al que clonar, y para que, sin el, se
                                    vea que faltan lineas.
                                --}}
                                <tr class="linea-plantilla d-none" id="lineaPlantilla">
                                    <td>
                                        <select class="form-select select2 producto-linea" name="productos[__i__][producto_id]">
                                            <option value="">Seleccione el material</option>
                                            @foreach($productos as $producto)
                                                <option
                                                    value="{{ $producto->id }}"
                                                    data-unidad="{{ $producto->unidad_medida }}"
                                                    data-existencia="{{ rtrim(rtrim(number_format($producto->existencia, 3), '0'), '.') ?: '0' }}"
                                                >
                                                    {{ $producto->nombre }} ({{ $producto->unidad_medida }})
                                                    — hay {{ rtrim(rtrim(number_format($producto->existencia, 3), '0'), '.') ?: '0' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            class="form-control cantidad-linea"
                                            name="productos[__i__][cantidad]"
                                            step="0.001"
                                            min="0.001"
                                            value="1"
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            class="form-control costo-linea"
                                            name="productos[__i__][costo_unitario]"
                                            step="0.01"
                                            min="0"
                                            value="0.00"
                                        >
                                    </td>
                                    <td class="text-end fw-semibold subtotal-linea">0.00</td>
                                    <td class="text-center">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light quitar-linea"
                                            title="Quitar esta línea"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-danger d-none" id="errorLineas"></div>

                    {{-- El resumen, que se ve mientras se rellena --}}
                    <div class="row justify-content-end mt-3">
                        <div class="col-md-5">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-end text-muted">Subtotal</td>
                                        <td class="text-end fw-semibold" id="resumenSubtotal">0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="text-end text-muted">Impuesto</td>
                                        <td class="text-end fw-semibold" id="resumenImpuesto">0.00</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td class="text-end fw-semibold">Total</td>
                                        <td class="text-end fw-bold fs-6" id="resumenTotal">0.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p class="form-text mt-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        El subtotal es la suma de las líneas y el total es el
                        subtotal más el impuesto. Los calcula el servidor al
                        guardar; aquí solo se previsualizan.
                    </p>

                </div>
            </div>
        </div>

        {{-- ============================ los botones ============================ --}}

        <div class="col-xl-12">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" id="btnGuardarCompra">
                    <i class="bi bi-check me-1"></i>
                    {{ $esEdicion ? 'Guardar cambios' : 'Registrar compra' }}
                </button>

                <a
                    href="{{ $esEdicion ? route('inventario.compras.show', $compra) : route('inventario.compras.index') }}"
                    class="btn btn-light"
                >
                    Cancelar
                </a>
            </div>
        </div>

    </div>

    {{--
        Las lineas que ya tiene la compra, para que el javascript las
        ponga en la rejilla.

        Van en un json y no escritas en el html ya pintado, porque leerlas
        de los inputs seria copiar los valores de un sitio a otro: en
        cuanto el servidor cambiara una linea, la copia seguiria
        enseñando lo viejo sin que nada lo dijera, y no habria forma de
        saber cual de los dos tiene razon.

        En el alta va vacio, y entonces la rejilla arranca con una linea
        en blanco.
    --}}
    <script type="application/json" id="datosLineas">
        @json($lineasParaJs)
    </script>

</form>
