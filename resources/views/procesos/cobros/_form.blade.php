{{--
    El formulario de un cobro: la cabecera y las lineas de ingreso.

    ===========================================================================
    LA ESTRUCTURA, Y POR QUÉ ESTÁ ASÍ
    ===========================================================================

    Primero la cabecera —cliente, fecha, caja, método, moneda— y después las lineas,
    porque las lineas dependen del cliente: son sus ingresos, y no se pueden offerser
    antes de saber a quien se le cobra. Al cambiar el cliente se sustituye la lista
    entera por ajax.

    Y el total va arriba, no abajo, aunque salga de las lineas. Va arriba porque es lo
    unico de la pantalla que no cambia nunca una vez elegido el cliente y el importe,
    y porque quien esta rellenando necesita verlo mientras escribe para saber si
    el total que tiene delante es el que espera.

    ===========================================================================
    LOS TRES DESPLEGABLES SOLO OFRECEN LO ACTIVO
    ===========================================================================

    Caja, método de pago y moneda. Un cobro finalizado es una foto de lo que se
    cobró y puede seguir viendo una caja que hoy está desactivada —la caja se
    desactiva cuando ya no se usa, no cuando dejó de existir—, de modo que la vista
    de la cabecera al editar SÍ tiene que poder mostrar la que se usó. Por eso el
    desplegable ofrece los activos y, si el cobro ya tiene una caja que no está entre
    ellos, se añade una opción con el texto de aviso, para que se vea cuál era en
    vez de que el desplegable aparezca con otra seleccionada sin decir nada.

    ===========================================================================
    LA REJILLA DE LÍNEAS, Y EL `data-nombre`
    ===========================================================================

    Igual que en las compras: la fila plantilla va oculta, lleva `data-nombre` y no
    `name`, y el javascript la clona y le pone el nombre real con el índice de la
    fila.

    Y el botón de agregar línea empieza DESHABILITADO, y eso no es un descuido de
    maquetación: hasta que no haya cliente los ingresos son una lista vacía y el
    botón no llevaría a nada. Habilitarlo solo cuando haya ingresos evita el
    pulsón perdido y el error de "no hay ninguna línea" que sale después.

    La razón de que el total vaya arriba del todo, y no debajo de la rejilla como en
    las compras, es que aquí cambia con cada línea que se añade. Abajo queda fuera
    de la vista en cuanto la rejilla crece, y quien está rellenando necesita verlo
    para saber si lleva la cuenta bien.

    Y el `data-nombre` en vez de `name` es lo que explica la cabecera de este
    archivo: un campo con `name` en una fila oculta se manda igual, y el servidor
    validaría una línea que no existe.
--}}
<form
    method="POST"
    action="{{ $esEdicion
        ? route('procesos.cobros.update', $cobro)
        : route('procesos.cobros.store') }}"
    id="formCobro"
>
    @csrf

    @if ($esEdicion)
        @method('PUT')
    @endif

    <div class="row g-3">

        {{-- ============================================================
             LA CABECERA
             ============================================================ --}}

        <div class="col-12">
            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">Cabecera del cobro</h5>
                        <p class="text-muted mb-0 small">
                            A quién se le cobra, cuándo, por dónde entró el dinero y con qué.
                        </p>
                    </div>
                </div>

                <div class="row g-3">

                    {{-- El codigo no se pide: lo pone el sistema. --}}
                    @if ($esEdicion)
                        <div class="col-12 col-md-3">
                            <label class="form-label">Código</label>
                            <input type="text" class="form-control" value="{{ $cobro->codigo }}" disabled>
                            <div class="form-text">
                                Lo pone el sistema y no se puede cambiar.
                            </div>
                        </div>
                    @endif

                    <div class="col-12 {{ $esEdicion ? 'col-md-3' : 'col-md-4' }}">
                        <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select
                            id="cliente_id"
                            name="cliente_id"
                            class="form-select select2"
                            data-select2-opciones='{"placeholder":"Escriba para buscar el cliente","allowClear":false}'
                            required
                        >
                            <option value="">Seleccione el cliente</option>
                            @foreach ($clientes as $cliente)
                                <option
                                    value="{{ $cliente->id }}"
                                    @selected(old('cliente_id', $cobro->cliente_id) == $cliente->id)
                                >
                                    {{ $cliente->nombre }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Las líneas son los ingresos de este cliente.
                        </div>
                    </div>

                    <div class="col-12 col-md-3">
                        <label for="fecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="fecha"
                            name="fecha"
                            class="form-control flatpickr-fecha"
                            data-calendario="propio"
                            placeholder="dd/mm/aaaa"
                            value="{{ old('fecha', $cobro->fecha?->format('d/m/Y') ?? now()->format('d/m/Y')) }}"
                            required
                            autocomplete="off"
                        >
                    </div>

                    <div class="col-12 col-md-3">
                        <label for="moneda_id" class="form-label">Moneda <span class="text-danger">*</span></label>
                        <select
                            id="moneda_id"
                            name="moneda_id"
                            class="form-select"
                            required
                        >
                            @foreach ($monedas as $moneda)
                                <option
                                    value="{{ $moneda->id }}"
                                    data-codigo="{{ $moneda->codigo }}"
                                    @selected(old('moneda_id', $cobro->moneda_id) == $moneda->id)
                                >
                                    {{ $moneda->codigo }} — {{ $moneda->nombre }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            La de este cobro, y la de todas sus líneas.
                        </div>
                    </div>

                    <div class="col-12 col-md-3">
                        <label for="caja_id" class="form-label">Caja <span class="text-danger">*</span></label>
                        <select id="caja_id" name="caja_id" class="form-select" required>
                            <option value="">Seleccione la caja</option>

                            {{--
                                Si el cobro ya tiene una caja que hoy no está activa, se
                                añade con su nombre y el aviso. Sin esto, el desplegable
                                se abriría con la primera activa seleccionada —porque
                                el value del cobro no está entre las opciones— y el
                                guardado movería el cobro a otra caja sin que nadie
                                lo pidiera.
                            --}}
                            @php
                                $cajaEnUso = $cajas->firstWhere('id', $cobro->caja_id);

                                if ($cajaEnUso === null && $cobro->caja_id) {
                                    $cajaEnUso = \App\Models\Cajas::withTrashed()->find($cobro->caja_id);
                                }
                            @endphp

                            @if ($cajaEnUso && ! $cajaEnUso->estado)
                                <option value="{{ $cajaEnUso->id }}" selected>
                                    {{ $cajaEnUso->nombre }} — está desactivada
                                </option>
                            @endif

                            @foreach ($cajas as $caja)
                                <option
                                    value="{{ $caja->id }}"
                                    @selected(old('caja_id', $cobro->caja_id) == $caja->id)
                                >
                                    {{ $caja->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label for="metodo_pago_id" class="form-label">Método de pago <span class="text-danger">*</span></label>
                        <select id="metodo_pago_id" name="metodo_pago_id" class="form-select" required>
                            <option value="">Seleccione el método</option>

                            @php
                                $metodoEnUso = $metodos->firstWhere('id', $cobro->metodo_pago_id);

                                if ($metodoEnUso === null && $cobro->metodo_pago_id) {
                                    $metodoEnUso = \App\Models\MetodosPagos::withTrashed()
                                        ->find($cobro->metodo_pago_id);
                                }
                            @endphp

                            @if ($metodoEnUso && ! $metodoEnUso->estado)
                                <option value="{{ $metodoEnUso->id }}" selected>
                                    {{ $metodoEnUso->nombre }} — está inactivo
                                </option>
                            @endif

                            @foreach ($metodos as $metodo)
                                <option
                                    value="{{ $metodo->id }}"
                                    @selected(old('metodo_pago_id', $cobro->metodo_pago_id) == $metodo->id)
                                >
                                    {{ $metodo->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label for="referencia" class="form-label">Referencia</label>
                        <input
                            type="text"
                            id="referencia"
                            name="referencia"
                            class="form-control"
                            maxlength="100"
                            placeholder="Recibo, nota, número de transferencia"
                            value="{{ old('referencia', $cobro->referencia) }}"
                        >
                        <div class="form-text">
                            Lo que deja el cliente como justificante. Opcional.
                        </div>
                    </div>

                    <div class="col-12 col-md-3">
                        <label for="estado" class="form-label">Estado <span class="text-danger">*</span></label>
                        <select id="estado" name="estado" class="form-select" required>
                            @foreach (\App\Models\Cobro::ESTADOS as $valor => $datos)
                                <option
                                    value="{{ $valor }}"
                                    @selected(old('estado', $cobro->estado ?? \App\Models\Cobro::ESTADO_PENDIENTE) == $valor)
                                >
                                    {{ $datos['texto'] }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text" id="ayudaEstado">
                            Pendiente: se anotó, todavía no llegó. Finalizada: el dinero
                            ya está en la caja.
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea
                            id="observaciones"
                            name="observaciones"
                            class="form-control"
                            rows="2"
                            maxlength="1000"
                            placeholder="Lo que haga falta recordar de este cobro"
                        >{{ old('observaciones', $cobro->observaciones) }}</textarea>
                    </div>

                </div>

            </div>
        </div>

        {{-- ============================================================
             LAS LÍNEAS
             ============================================================ --}}

        <div class="col-12">
            <div class="widget-content widget-content-area br-8">

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">¿A qué ingresos se imputa este dinero?</h5>
                        <p class="text-muted mb-0 small">
                            Cada línea dice cuánto de este dinero va a un ingreso.
                            Un ingreso puede aparecer en varios cobros, porque se
                            cobra a plazos.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-sm btn-light"
                        id="btnAgregarLinea"
                        disabled
                        title="Elija primero el cliente"
                    >
                        <i class="bi bi-plus-lg me-1"></i>
                        Agregar línea
                    </button>
                </div>

                {{-- El total, arriba, porque es lo único que no cambia. --}}
                <div class="d-flex justify-content-end mb-3">
                    <div class="text-end">
                        <div class="text-secondary small">Total del cobro</div>
                        <div class="fs-4 fw-bold" id="totalCobro">0.00</div>
                        {{--
                            Y de que moneda es. El total de aqui es una suma de lo
                            que se ha puesto en las lineas, y cada linea va en la
                            moneda del cobro, asi que avisar de la moneda no es un
                            adorno: sin esto, un cobro en dolares se lee igual que
                            uno en cordoba y solo se distingue por el numero.
                        --}}
                        <div class="text-secondary small" id="textoMonedaTotal">
                            en la moneda elegida
                        </div>
                    </div>
                </div>

                <div class="alert alert-danger d-none" id="errorLineas"></div>

                <div class="table-responsive">
                    {{--
                        El ancho fijo impide que una etiqueta larga del Select2
                        agrande la primera columna y comprima los campos editables.
                        En pantallas estrechas la tabla conserva un ancho legible y
                        el contenedor permite desplazarse horizontalmente.
                    --}}
                    <table
                        class="table align-middle"
                        id="tablaLineas"
                        style="table-layout: fixed; min-width: 760px;"
                    >
                        <thead class="table-light">
                            <tr>
                                <th style="width: 44%">Ingreso</th>
                                <th style="width: 20%">De un total de</th>
                                <th style="width: 18%">Importe de esta línea</th>
                                <th style="width: 15%">Nota</th>
                                <th style="width: 3%"></th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoLineas">
                            {{--
                                Las líneas existentes las pinta el javascript leyendo
                                $lineasParaJs. Aquí hay una fila de ejemplo para que el
                                script tenga un molde al que clonar, y para que sin
                                él se vea que faltan líneas.

                                Y el `data-nombre` en vez de `name` es lo que explica
                                la cabecera de este archivo: un campo con `name` en
                                una fila oculta se manda igual, y el servidor
                                validaría una línea que no existe.
                            --}}
                            <tr class="linea-plantilla d-none" id="lineaPlantilla">
                                <td>
                                    <select
                                        class="form-select ingreso-linea"
                                        data-nombre="ingresos[__i__][ingreso_id]"
                                        disabled
                                    >
                                        <option value="">Seleccione el ingreso</option>
                                        @foreach ($ingresos as $ingreso)
                                            <option
                                                value="{{ $ingreso['id'] }}"
                                                data-total="{{ $ingreso['total'] }}"
                                                data-moneda="{{ $ingreso['moneda_id'] }}"
                                                data-cobrado="{{ $ingreso['cobrado'] }}"
                                                data-texto="{{ $ingreso['tipo'] }} de {{ $ingreso['codigo_orden'] }} del {{ $ingreso['fecha'] }}"
                                            >
                                                {{ $ingreso['tipo'] }} — {{ $ingreso['codigo_orden'] }} — {{ $ingreso['fecha'] }}
                                                ({{ number_format($ingreso['total'], 2) }})
                                                @if ($ingreso['cobrado'] > 0)
                                                    — cobrado {{ number_format($ingreso['cobrado'], 2) }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-secondary resumen-ingreso" style="font-size: .78rem;">
                                    —
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        class="form-control monto-linea"
                                        data-nombre="ingresos[__i__][monto]"
                                        step="0.01"
                                        min="0.01"
                                        value=""
                                        disabled
                                    >
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        class="form-control nota-linea"
                                        data-nombre="ingresos[__i__][observaciones]"
                                        maxlength="255"
                                        placeholder="Opcional"
                                        disabled
                                    >
                                </td>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light quitar-linea"
                                        title="Quitar esta línea"
                                    >
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-muted mb-0 mt-2 small" id="avisoSinCliente">
                    Elija un cliente arriba para ver sus ingresos y poder repartirlos.
                </p>

            </div>
        </div>

    </div>

    {{-- ================================================================
         LOS DATOS QUE NECESITA EL JAVASCRIPT
         ================================================================= --}}

    {{--
        El json se arma en el controlador y no aqui.

        Es lo unico que hace falta: en la vista, @json([...]) con una sintaxis de
        php dentro —array corto con claves y operadores ternarios— se compila a
        json_encode([...]) con los valores metidos dentro a pelo, y el resultado
        suele ser un "Unclosed '['" que sale en la vista compilada, en una linea
        que ya no es la que se escribio, y no dice nada de cobros.

        Aparte de que el compilador de blade no siempre acepta la mezcla, el dato
        tiene que estar en el controlador igual: el javascript lo necesita en un
        solo sitio y armarlo aqui significaria duplicar la cuenta en el sitio que
        la hace y en el sitio que la cuenta.

        Y se manda como <script type="application/json">, no como una variable de
        javascript: dentro de un <script> normal, un </script> dentro de un dato
        cortaria el bloque entero, y una descripcion de ingreso con eso dentro se
        lleva por delante la pagina.
    --}}
    <script type="application/json" id="datosCobro">{!! $datosParaJs !!}</script>

    <div class="d-flex justify-content-between mt-4 flex-wrap gap-2">
        <a href="{{ route('procesos.cobros.index') }}" class="btn btn-light">Volver</a>

        <button type="submit" class="btn btn-primary" id="btnGuardarCobro">
            <span class="spinner-border spinner-border-sm d-none me-1" id="spinnerGuardar"></span>
            <i class="bi bi-check-lg me-1"></i>
            Guardar cobro
        </button>
    </div>

</form>
