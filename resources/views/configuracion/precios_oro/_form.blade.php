{{--
    El formulario de un precio del oro.

    Va dentro de un modal, en la pantalla de la lista. Son cinco campos y una
    pantalla entera para eso seria mas ruido que ayuda: el usuario esta
    mirando una serie de fechas, elige el dia que le falta y lo rellena sin
    salir de ahi.

    La unidad es un desplegable con la lista cerrada y no un campo de texto, y
    no por comodidad. La columna es una cadena de veinte caracteres, asi que
    "Gramo", "gramo" y "gr" serían tres unidades distintas: el mismo gramo
    existiria dos veces en la serie y al indizar por fecha, unidad y moneda
    las dos filas coexistirian sin que nada lo notara. Con la lista cerrada,
    el gramo es el gramo.

    El precio admite cero a proposito, y el texto de debajo lo explica, porque
    es la parte que mas confunde: aqui el cero no es un error ni un precio de
    cero, quiere decir "de este dia no se sabe el precio". Cuando eso pasa, al
    valorar no se multiplica por cero: se usa el ultimo precio que si se sepa.
    En el tipo de cambio el cero si esta prohibido, y por lo mismo.

    El campo del precio es numerico y sin el atributo de paso, para que el
    separador decimal lo ponga el navegador segun como esta la maquina y no
    segun como se le antoje al servidor.
--}}
<div class="row g-3">

    <div class="col-md-4">
        <label for="fecha" class="form-label">
            Día <span class="text-danger">*</span>
        </label>

        <input
            type="date"
            class="form-control @error('fecha') is-invalid @enderror"
            id="fecha"
            name="fecha"
            value="{{ old('fecha', $precioOro->fecha ?? '') }}"
            required
        >

        @error('fecha')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            El precio de ese día, tal como lo cotiza el banco.
        </div>
    </div>

    <div class="col-md-4">
        <label for="precio" class="form-label">
            Precio <span class="text-danger">*</span>
        </label>

        <input
            type="number"
            step="0.0001"
            min="0"
            class="form-control @error('precio') is-invalid @enderror"
            id="precio"
            name="precio"
            value="{{ old('precio', $precioOro->precio ?? '') }}"
            placeholder="78.4521"
            required
        >

        @error('precio')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            Con punto o con coma, pero solo uno de los dos como decimal. El
            <strong>cero</strong> está permitido y quiere decir «de este día no
            se sabe el precio»: al valorar se usará el último precio que sí se
            sepa, no cero.
        </div>
    </div>

    <div class="col-md-4">
        <label for="unidad" class="form-label">
            Unidad <span class="text-danger">*</span>
        </label>

        <select
            class="form-select @error('unidad') is-invalid @enderror"
            id="unidad"
            name="unidad"
            required
        >
            <option value="">Elija la unidad</option>

            @foreach($unidades as $valor => $texto)
                <option
                    value="{{ $valor }}"
                    @selected(old('unidad', $precioOro->unidad ?? 'gramo') === $valor)
                >
                    {{ $texto }}
                </option>
            @endforeach
        </select>

        @error('unidad')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            El gramo es con el que se lleva todo el oro del sistema. La onza
            troy son 31,1034768 gramos, no 31.
        </div>
    </div>

    <div class="col-md-6">
        <label for="moneda_id" class="form-label">
            Moneda <span class="text-danger">*</span>
        </label>

        <select
            class="form-select select2 @error('moneda_id') is-invalid @enderror"
            id="moneda_id"
            name="moneda_id"
            required
        >
            <option value="">Elija la moneda</option>

            @foreach($monedas as $moneda)
                <option
                    value="{{ $moneda->id }}"
                    @selected((int) old('moneda_id', $precioOro->moneda_id ?? 0) === (int) $moneda->id)
                >
                    {{ $moneda->nombre }} ({{ $moneda->simbolo ?: $moneda->codigo }})
                </option>
            @endforeach
        </select>

        @error('moneda_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            En qué moneda está cotizado ese precio. El tipo de cambio de esa
            moneda el día de ese precio es lo que lo pasa a córdobas.
        </div>
    </div>

    <div class="col-md-6">
        <label for="fuente" class="form-label">
            Fuente
        </label>

        <input
            type="text"
            class="form-control @error('fuente') is-invalid @enderror"
            id="fuente"
            name="fuente"
            value="{{ old('fuente', $precioOro->fuente ?? '') }}"
            maxlength="150"
            placeholder="Banco Central de Nicaragua"
        >

        @error('fuente')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="form-text">
            No se toca al importar: si lo corrigió a mano y anotó de dónde lo
            sacó, el archivo no se lo pisa encima.
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
            value="{{ old('observaciones', $precioOro->observaciones ?? '') }}"
            maxlength="1000"
            placeholder="Día festivo, precio del mercado negro, lo que haga falta"
        >

        @error('observaciones')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

</div>
