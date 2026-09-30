{{--
    Campos de un ingreso de la orden.

    Es el mismo formulario que el de los costos con otras cuatro cosas, y esa
    diferencia se explica aqui porque no es solo cambiar el nombre de los campos:

    EL TIPO DE INGRESO ES OBLIGATORIO Y NO HAY CATEGORIA LIBRE. En los costos
    se elige una categoria del catalogo; en los ingresos tambien, pero el
    catalogo es de cuatro nombres que puso el taller y que significan cosas
    distintas: servicio de procesamiento, participacion en oro, venta de oro y
    otro. Un ingreso sin tipo no se sabe que es, y "un ingreso" no es una
    respuesta a la pregunta de cuanto entro y por que.

    LA UNIDAD DE MEDIDA LA PONE EL USUARIO Y ES LIBRE. En los costos no hace
    falta porque lo que se consume se mide en kilos o en litros y sale solo. En
    los ingresos el oro se mide en gramos, la participacion tambien, y el
    servicio de procesamiento no se mide en nada: es una tarifa. De ahi que la
    unidad sea texto libre y no una lista cerrada como la del precio del oro —
    que si la lista es cerrada, porque alli "gramo" y "Gramo" serian dos series
    distintas y el mismo gramo existiria dos veces.

    Y LA CANTIDAD PUEDE SER UNO. El taller cobra un servicio de procesamiento
    por trabajo hecho, no por kilo, y en ese caso la cantidad es 1 con unidad
    "servicio". Por eso el campo no fuerza una unidad concreta.

    EL TOTAL NO SE ESCRIBE. Sale de multiplicar la cantidad por el precio
    unitario, y lo hace el servidor. Aqui solo se previsualiza, que es una
    multiplicacion y no necesita nada del servidor. El equivalente en cordoba
    NO se previsualiza: necesita el tipo de cambio de la fecha, y pedirlo al
    servidor en cada tecla seria una consulta por pulsacion. Se muestra el que
    devolvio el ultimo guardado.

    Y hay un aviso para cuando la moneda no es la base: avisa de que el tipo de
    cambio que se va a aplicar es el de la fecha, y no el de hoy. Es el error
    que mas caro sale en un taller que cobra en dolares y lleva la contabilidad
    en cordoba, porque un tipo de cambio distinto convierte todo lo del mes y
    el error se ve un trimestre despues.
--}}
<div class="row g-3">

    {{-- Tipo de ingreso --}}
    <div class="col-md-4">
        <label for="ingresoTipo" class="form-label">
            Tipo de ingreso <span class="text-danger">*</span>
        </label>

        <select name="tipo_ingreso_id" id="ingresoTipo" class="form-select select2" required>
            <option value="">Seleccione...</option>
            @foreach($tiposIngreso as $tipo)
                <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
            @endforeach
        </select>

        <small class="text-muted">
            Solo salen los tipos activos. Si falta alguno que se usa, se activa
            en Configuración.
        </small>
    </div>

    {{-- Fecha --}}
    <div class="col-md-4">
        <label for="ingresoFecha" class="form-label">
            Fecha <span class="text-danger">*</span>
        </label>

        <input
            type="date"
            name="fecha"
            id="ingresoFecha"
            class="form-control"
            value="{{ $fechaPorDefecto }}"
            required
        >

        <small class="text-muted">
            El día del tipo de cambio, si el ingreso no es en cordoba.
        </small>
    </div>

    {{-- Moneda --}}
    <div class="col-md-4">
        <label for="ingresoMoneda" class="form-label">
            Moneda <span class="text-danger">*</span>
        </label>

        <select name="moneda_id" id="ingresoMoneda" class="form-select select2" required>
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
    <div class="col-md-12">
        <label for="ingresoDescripcion" class="form-label">
            Descripción
        </label>

        <input
            type="text"
            name="descripcion"
            id="ingresoDescripcion"
            class="form-control"
            maxlength="255"
            placeholder="Proceso de la colada de setiembre, lo que haga falta"
        >

        <small class="text-muted">
            Opcional. Con el tipo ya se sabe de qué es el ingreso; esto es para
            el detalle de qué partida es.
        </small>
    </div>

    {{--
        Los importes. El total se previsualiza aqui para que se vea mientras se
        escribe, pero al guardar lo vuelve a calcular el servidor: es el que
        manda, y por eso no se manda en el formulario.
    --}}
    <div class="col-md-2">
        <label for="ingresoCantidad" class="form-label">
            Cantidad <span class="text-danger">*</span>
        </label>

        <input
            type="number"
            name="cantidad"
            id="ingresoCantidad"
            class="form-control"
            value="1"
            min="0.0001"
            step="0.0001"
            required
        >
    </div>

    <div class="col-md-2">
        <label for="ingresoUnidad" class="form-label">
            Unidad
        </label>

        <input
            type="text"
            name="unidad_medida"
            id="ingresoUnidad"
            class="form-control"
            maxlength="20"
            placeholder="g, kg, servicio"
        >

        <small class="text-muted">Gramo, kilo, o lo que sea.</small>
    </div>

    <div class="col-md-2">
        <label for="ingresoUnitario" class="form-label">
            Precio unitario <span class="text-danger">*</span>
        </label>

        <input
            type="number"
            name="precio_unitario"
            id="ingresoUnitario"
            class="form-control"
            value="0.00"
            min="0"
            step="0.01"
            required
        >
    </div>

    <div class="col-md-3">
        <label class="form-label text-muted">Total</label>
        <div class="input-group">
            <input
                type="text"
                id="ingresoTotal"
                class="form-control fw-semibold"
                value="0.00"
                readonly
            >
            <span class="input-group-text" id="ingresoTotalMoneda">—</span>
        </div>
    </div>

    <div class="col-md-3">
        <label class="form-label text-muted">Total en NIO</label>
        <div class="input-group">
            <input
                type="text"
                id="ingresoTotalNio"
                class="form-control"
                value="—"
                readonly
            >
            <span class="input-group-text" id="ingresoTipoCambio">—</span>
        </div>
    </div>

    <div class="col-12">
        {{--
            El aviso va debajo de todo y no al lado de un campo porque son tres
            frases, y al lado de un campo en una pantalla estrecha cada una se
            parte en dos lineas y no se entiende ninguna. Ademas el que va a
            leerlo de verdad es el que esta dudando si el cambio que se va a
            aplicar es el que cree, y esa duda se resuelve leyendo entero.
        --}}
        <div class="alert d-none mb-0" id="ingresoAviso"></div>
    </div>

    <div class="col-12">
        <label for="ingresoObservaciones" class="form-label">Observaciones</label>
        <textarea
            name="observaciones"
            id="ingresoObservaciones"
            class="form-control"
            rows="2"
        ></textarea>
    </div>
</div>
