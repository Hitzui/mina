{{--
    El formulario de una valoracion de oro.

    Va dentro de un modal, en la pantalla de la lista. Son cuatro campos y un
    calculo, y ese calculo es la pantalla entera.

    NO HAY CAMPO DE VALOR, y es lo mas importante de este archivo.

    Lo que se guarda lo calcula el servidor con la serie de precios del oro y el
    tipo de cambio del dia. Si el valor se pudiera teclear, la pantalla tendria
    dos cifras para los mismos gramos —la que el usuario escribio y la que sale
    de la cuenta— y ninguna de las dos avisaria de que la otra existe. Un taller
    que factura sobre un valor tecleado a mano esta facturando sobre un numero
    que nadie ha comprobado.

    En su lugar hay un aviso que se rellena solo, y que va POR DEBAJO de los
    campos y a pantalla completa, no al lado del valor. La razon de que este
    abajo y no al lado es de espacio: la cuenta son tres lineas con nombres
    largos —"1,5000 g de oro fino de 2,0000 g", "4.700,0000 el gramo del
    29/09/2026", "36,6243 córdobas por dólar"— y si comparte fila con un
    selector de moneda en una pantalla estrecha, cada linea se parte en dos y
    la cuenta deja de leerse. Abajo, con todo el ancho, se lee de una pasada.

    LAS DOS MONEDAS, que no son la misma cosa y por eso hay dos campos.

    La del precio es donde se busca el precio del gramo: el taller carga lo que
    cotiza el banco, y si el banco cotiza en dolares, el precio esta en dolares.
    La del valor es en la que se guarda el resultado, y lo normal es que sea la
    moneda base, que es donde esta la contabilidad.

    Cuando coinciden —el caso normal— no hace falta tipo de cambio para nada y
    el aviso no pone la linea. Cuando no coinciden, hace falta, y el aviso dice
    cuanto se aplico, porque de ese numero depende el valor final.

    LOS GRAMOS VALORADOS, y por que no son los de la recuperacion.

    Si la recuperacion tiene pureza, se valoran los gramos finos: una partida
    de 2 gramos al 75 % tiene 1,5 gramos de oro, y valorar los 2 seria pagar de
    mas por material que no era oro. Si no tiene pureza, se valoran los que
    salieron, que es lo unico que hay.

    Deliberadamente NO es la misma regla que la del precio del oro, donde el
    cero significa "de ese dia no se sabe": aqui el cero es un hecho —una partida
    de la que no salio oro vale cero— y ahi era una falta de dato. Las dos se
    llaman "el valor" y se tratan distinto a proposito.

    LAS RECUPERACIONES QUE NO SE PUEDEN VALORAR NO SE OCULTAN.

    Las de una orden cancelada salen en el desplegable marcadas como tal, y el
    aviso explica por que. Filtrarlas del desplegable seria mas limpio de mirar
    y peor de usar: el usuario ve que su partida no esta, no sabe si se borro o
    si se le esta ocultando, y acaba buscando por otra parte. Verla con la
    razon al lado es lo unico que le dice que hacer.

    Y las que YA estan valoradas tambien salen, marcadas, porque la regla es
    que una partida tiene un solo valor y lo que se hace en ese caso es
    corregir el que hay. Es la unica forma de que alguien que quiere cambiar un
    valor por otro se entere de que tiene que editar, y no crear.
--}}
<div class="row g-3">

    @php
        /*
         * La moneda base se busca una vez, antes del bucle.
         *
         * Buscarla dentro del bucle funcionaria igual pero se ejecutaria una
         * vez por moneda, y el mas probable error al escribir esto es ponerlo
         * dentro por costumbre: "old(...) ?? es_moneda_base ? id : 0" no es lo
         * que parece. El ?? pesa mas que el ternario, asi que eso se lee como
         * "(old(...) ?? es_moneda_base) ? id : 0", y con el campo vacio —que es
         * el caso del alta, que es el normal— el resultado no es el id de la
         * base sino un 1 o un 0. Se calcula fuera y se compara limpio.
         */
        $monedaBaseId = (int) ($monedas->firstWhere('es_moneda_base', true)?->id ?? 0);
    @endphp

    <div class="col-md-7">
        <label for="recuperacion_id" class="form-label">
            Recuperación que se valora <span class="text-danger">*</span>
        </label>

        <select
            class="form-select select2 @error('recuperacion_id') is-invalid @enderror"
            id="recuperacion_id"
            name="recuperacion_id"
            required
        >
            <option value="">Elija la recuperación</option>

            @foreach($recuperaciones as $recuperacion)
                @php
                    $orden = $recuperacion->orden_trabajo;
                    $cancelada = $orden && (int) $orden->estado === \App\Models\OrdenesTrabajo::ESTADO_CANCELADA;
                    $valorada = $recuperacion->valoracion !== null;
                @endphp

                <option
                    value="{{ $recuperacion->id }}"
                    @selected((int) old('recuperacion_id', $valoracion->recuperacion_id ?? 0) === (int) $recuperacion->id)
                >
                    {{ $orden?->codigo ?? 'sin orden' }} — {{ $recuperacion->fecha->format('d/m/Y') }} — {{ number_format((float) $recuperacion->gramos, 4) }} g{{ $recuperacion->purezaEnPorcentaje() === null ? '' : ' · ' . number_format($recuperacion->purezaEnPorcentaje(), 2) . ' %' }}
                    @if($valorada)
                        — ya valorada
                    @elseif($cancelada)
                        — orden cancelada, no valorable
                    @endif
                </option>
            @endforeach
        </select>

        @error('recuperacion_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Cada partida tiene un solo valor. Las que ya están valoradas se
            corrigen, no se duplican; las de una orden cancelada no admiten
            valoración, porque no se hizo el trabajo.
        </div>
    </div>

    <div class="col-md-5">
        <label for="fechaValoracion" class="form-label">
            Día de la valoración <span class="text-danger">*</span>
        </label>

        <input
            type="date"
            class="form-control @error('fecha') is-invalid @enderror"
            id="fechaValoracion"
            name="fecha"
            value="{{ old('fecha', $valoracion?->fecha?->format('Y-m-d') ?? '') }}"
            required
        >

        @error('fecha')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            El día del precio que se usa. Si ese día no hubo cotización, se toma
            el último precio conocido que haya antes.
        </div>
    </div>

    <div class="col-md-6">
        <label for="precio_moneda" class="form-label">
            El precio del oro está en <span class="text-danger">*</span>
        </label>

        <select
            class="form-select @error('precio_moneda') is-invalid @enderror"
            id="precio_moneda"
            name="precio_moneda"
            required
        >
            <option value="">Elija la moneda</option>

            @foreach($monedas as $moneda)
                <option
                    value="{{ $moneda->id }}"
                    @selected((int) old('precio_moneda', $valoracion?->precio_oro?->moneda_id ?? $precioMonedaPorDefecto) === (int) $moneda->id)
                >
                    {{ $moneda->codigo }} — {{ $moneda->nombre }}
                </option>
            @endforeach
        </select>

        @error('precio_moneda')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            En la moneda en que el banco cotiza el oro. Viene marcada la del
            último precio que se cargó, que es donde están los precios del taller.
        </div>
    </div>

    <div class="col-md-6">
        <label for="moneda_id" class="form-label">
            El valor se guarda en <span class="text-danger">*</span>
        </label>

        <select
            class="form-select @error('moneda_id') is-invalid @enderror"
            id="moneda_id"
            name="moneda_id"
            required
        >
            <option value="">Elija la moneda</option>

            @foreach($monedas as $moneda)
                <option
                    value="{{ $moneda->id }}"
                    @selected((int) old('moneda_id', $valoracion?->moneda_id ?? $monedaBaseId) === (int) $moneda->id)
                >
                    {{ $moneda->codigo }} — {{ $moneda->nombre }}@if($moneda->es_moneda_base) (base)@endif
                </option>
            @endforeach
        </select>

        @error('moneda_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            En la moneda en que se va a llevar el importe. Si es la misma que el
            precio, no se usa ningún tipo de cambio.
        </div>
    </div>

    {{--
        La cuenta, que se rellena sola.

        Va debajo de todo y a pantalla completa porque son tres lineas con
        nombres largos, y explicarlo aqui y no al lado de un campo es lo que
        hace que en un móvil se lea entero en vez de partido en seis.

        Se escribe con un esqueleto y no con el texto ya puesto porque al abrir
        el modal todavia no se sabe de que partida se trata: sin recuperacion
        elegida no hay gramos que multiplicar. El javascript lo rellena en
        cuanto se elige una, y si no se puede hacer —no hay ningun precio
        anterior a ese dia— lo dice con el motivo, que es lo que evita que el
        boton de guardar quede pulsado en silencio.
    --}}
    <div class="col-12">
        <div id="valoracionCalculo" class="alert alert-light border d-none" role="alert">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-calculator me-2"></i>
                <span class="fw-semibold">La cuenta</span>
                <span class="text-muted small ms-auto">No se escribe: sale de los gramos y del precio de ese día</span>
            </div>

            <div id="valoracionCalculoLineas"></div>
        </div>
    </div>

    <div class="col-12">
        <label for="observaciones" class="form-label">
            Observaciones
        </label>

        <input
            type="text"
            class="form-control @error('observaciones') is-invalid @enderror"
            id="observaciones"
            name="observaciones"
            value="{{ old('observaciones', $valoracion?->observaciones ?? '') }}"
            maxlength="1000"
            placeholder="Cobro de la colada de setiembre, lo que haga falta"
        >

        @error('observaciones')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

</div>
