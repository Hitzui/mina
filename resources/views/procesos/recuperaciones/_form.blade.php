{{--
    El formulario de una recuperacion de oro.

    Va dentro de un modal, en la pantalla de la lista. Son cinco campos y la
    mayoria son numeros: una pantalla entera para escribir una fecha y unos
    gramos seria mas sitio en blanco que otra cosa.

    LA PUREZA, que es el campo que mas cuidado pide.

    La columna guarda una fraccion, no un porcentaje: 0,915 es el 91,5 %. Es lo
    que dice la documentacion y evita que se guarden mil veces mas, que es lo
    que pasa cuando el 91,5 % se teclea como 91,5 y la columna no esta
    acotada.

    Es un campo que se presta a equivocarse de dos maneras, y las dos estan
    tapadas por el texto de al lado:

      - Teclear el porcentaje en vez de la fraccion. El servidor no lo admite
        —el maximo es uno— y el aviso lo dice con las dos formas de escribirlo
        para que no haya que adivinar cual se quiere.

      - Dejarlo en blanco cuando no se midio. Blank es una cosa y cero es otra:
        cero por ciento de pureza dice que el oro era puro cero, y lo que se
        quiere decir cuando nadie lo midio es que no se sabe. Blank guarda
        null, la lista lo enseña como "sin medir", y las dos cosas se
        distinguen de un vistazo.

    Los gramos en cero, en cambio, si se guardan, y con razon: una partida de
    la que no salio oro es un dato real, no una falta de dato. En el precio del
    oro el cero significaba "no se sabe" y por eso se trataba distinto, y no
    se puede aplicar el mismo criterio a las dos cosas solo porque las dos se
    llamen "el valor".

    El selector de orden lleva buscador. Hay ordenes de trabajo de sobra y el
    codigo es lo unico que se recuerda de ellas.
--}}
<div class="row g-3">

    <div class="col-md-8">
        <label for="orden_trabajo_id" class="form-label">
            Orden de trabajo <span class="text-danger">*</span>
        </label>

        <select
            class="form-select select2 @error('orden_trabajo_id') is-invalid @enderror"
            id="orden_trabajo_id"
            name="orden_trabajo_id"
            required
        >
            <option value="">Elija la orden</option>

            @foreach($ordenes as $orden)
                <option
                    value="{{ $orden->id }}"
                    @selected((int) old('orden_trabajo_id', $recuperacion->orden_trabajo_id ?? 0) === (int) $orden->id)
                >
                    {{ $orden->codigo }} — {{ $orden->cliente?->nombre ?? 'sin cliente' }}
                    @if($orden->estaCerrada())
                        ({{ strtolower($orden->estadoTexto()) }})
                    @endif
                </option>
            @endforeach
        </select>

        @error('orden_trabajo_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Las órdenes marcadas entre paréntesis están cerradas: admiten que
            se corrija lo que ya está escrito, pero no admitir recuperaciones
            nuevas.
        </div>
    </div>

    <div class="col-md-4">
        <label for="fechaRecuperacion" class="form-label">
            Día <span class="text-danger">*</span>
        </label>

        <input
            type="date"
            class="form-control @error('fecha') is-invalid @enderror"
            id="fechaRecuperacion"
            name="fecha"
            value="{{ old('fecha', $recuperacion->fecha ?? '') }}"
            required
        >

        @error('fecha')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            El día en que salió el oro del taller.
        </div>
    </div>

    <div class="col-md-6">
        <label for="gramos" class="form-label">
            Gramos recuperados <span class="text-danger">*</span>
        </label>

        <input
            type="number"
            step="0.0001"
            min="0"
            class="form-control @error('gramos') is-invalid @enderror"
            id="gramos"
            name="gramos"
            value="{{ old('gramos', $recuperacion->gramos ?? '') }}"
            placeholder="124.5678"
            required
        >

        @error('gramos')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Con punto o con coma, pero solo uno de los dos como decimal. El
            cero se admite: es una partida de la que no salió oro, y eso es un
            dato.
        </div>
    </div>

    <div class="col-md-6">
        <label for="pureza" class="form-label">
            Pureza
        </label>

        <input
            type="number"
            step="0.000001"
            min="0"
            max="1"
            class="form-control @error('pureza') is-invalid @enderror"
            id="pureza"
            name="pureza"
            value="{{ old('pureza', $recuperacion->pureza ?? '') }}"
            placeholder="0.915"
        >

        @error('pureza')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Como fracción, no como porcentaje: <strong>0,915 es el 91,5 %</strong>.
            Se deja en blanco cuando no se midió.
        </div>
    </div>

    {{--
        El aviso de la pureza mientras se escribe.

        Lo pinta el javascript, y se deja escondido y vacio de salida
        porque si no, el hueco aparece siempre y ocupa sitio sin decir
        nada. Se escribe aqui, debajo de la mitad de la fila, y no dentro
        de la celda de la pureza, porque el texto necesita su propio ancho:
        si comparte sitio con el campo, en una pantalla estrecha se parte
        en tres lineas de las que no se entiende ninguna.
    --}}
    <div class="col-12">
        <div id="purezaAviso" class="alert py-2 px-3 small d-none" role="alert"></div>
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
            value="{{ old('observaciones', $recuperacion->observaciones ?? '') }}"
            maxlength="1000"
            placeholder="Segunda partida de la colada, lo que haga falta"
        >

        @error('observaciones')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

</div>
